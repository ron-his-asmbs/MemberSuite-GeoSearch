<?php

namespace MemberSuiteGeoSearch;

use GuzzleHttp\Client;

class Plugin
{
    private $table_name;

    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'member_geodata';

        register_activation_hook(__FILE__, [$this, 'create_table']);

        add_action('wp', [$this, 'schedule_cron']);
        add_action('sync_membersuite_members', [$this, 'sync_members']);
        add_action('wp_ajax_ms_sync_batch', [$this, 'ajax_sync_batch']);
        add_action('wp_ajax_ms_find_members', [$this, 'ajax_find_members']);
        add_action('wp_ajax_nopriv_ms_find_members', [$this, 'ajax_find_members']); // allows public access

        add_shortcode('member_geosearch', [$this, 'geo_search_shortcode']);
        add_shortcode('member_search', [$this, 'member_search_shortcode']);
    }

    public function ajax_sync_batch()
    {
        check_ajax_referer('ms_sync_batch', 'nonce');

        $offset = intval($_POST['offset'] ?? 0);

        if ($offset === 0) {
            delete_transient('ms_members_cache');
            $this->log('ms_sync: cleared cache');
        }

        $this->log('ms_sync: offset = ' . $offset);

        $result = $this->sync_members_batch($offset);

        $this->log('ms_sync: result = ' . print_r($result, true));

        wp_send_json_success($result);
    }

    public function create_table()
    {
        global $wpdb;
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$this->table_name} (
        id BIGINT NOT NULL AUTO_INCREMENT,
        member_id VARCHAR(50) NOT NULL,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        email VARCHAR(150),
        city VARCHAR(100),
        state VARCHAR(100),
        country VARCHAR(100),
        member_type VARCHAR(100),
        latitude DECIMAL(10,7),
        longitude DECIMAL(10,7),
        surgery_types TEXT,
        last_updated DATETIME,
        PRIMARY KEY (id),
        UNIQUE KEY member_id (member_id)
    ) $charset_collate;";

        dbDelta($sql);
    }

    public function schedule_cron()
    {
        if (!wp_next_scheduled('sync_membersuite_members')) {
            wp_schedule_event(time(), 'daily', 'sync_membersuite_members');
        }
    }

    public function sync_members(): int
    {
        global $wpdb;
        set_time_limit(0);
        ignore_user_abort(true);

        // Clear cache so we get fresh data
        delete_transient('ms_members_cache');

        $offset = 0;
        $batch_size = 15;

        do {
            $result = $this->sync_members_batch($offset, $batch_size);
            $offset += $batch_size;
        } while (!$result['done']);

        return $result['total'];
    }

    public function sync_members_batch(int $offset = 0, int $batch_size = 15): array
    {
        global $wpdb;
        set_time_limit(120);    // Two minutes per batch

        // Fetch member list once and cache it
        $members = get_transient('ms_members_cache');
        $this->log('ms_sync: transient returned ' . (is_array($members) ? count($members) . ' members' : 'false'));

        if ($members === false || empty($members)) {
            $this->log('ms_sync: fetching fresh member list');
            $members = $this->executeSearchDirectoryIndividuals();
            $this->log('ms_sync: fetched ' . count($members) . ' members');
            if (!empty($members)) {
                set_transient('ms_members_cache', $members, HOUR_IN_SECONDS);
            }
        }

        if (empty($members)) {
            $this->log('ms_sync: members list is empty');
            return [
                'processed' => 0,
                'total' => 0,
                'done' => true,
            ];
        }

        $token = $this->getMSToken();
        $batch = array_slice($members, $offset, $batch_size);
        $count = 0;

        foreach ($batch as $entry) {
            $geo = $this->getMemberGeoData($entry['id'], $token);

            $wpdb->replace(
                $this->table_name,
                [
                    'member_id'      => $entry['id'],
                    'first_name'     => $entry['firstName'],
                    'last_name'      => $entry['lastName'],
                    'email'          => $geo['email']          ?? null,
                    'latitude'       => $geo['latitude']        ?? null,
                    'longitude'      => $geo['longitude']       ?? null,
                    'city'           => $geo['city']            ?? null,
                    'state'          => $geo['state']           ?? null,
                    'country'        => $geo['country']         ?? null,
                    'practice_line1' => $geo['practice_line1']  ?? null,
                    'practice_line2' => $geo['practice_line2']  ?? null,
                    'practice_zip'   => $geo['practice_zip']    ?? null,
                    'practice_phone' => $geo['practice_phone']  ?? null,
                    'member_type'    => $entry['designation']   ?? null,
                    'surgery_types'  => $geo['surgery_types']   ?? null,
                    'last_updated'   => current_time('mysql'),
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            $count++;
        }

        $processed = $offset + $count;
        $done = $processed >= count($members);

        // Clear cache when done
        if ($done) {
            delete_transient('ms_members_cache');
        }

        return [
            'processed' => $processed,
            'total' => count($members),
            'done' => $done,
        ];
    }

    private function get_members_near($lat, $lng, $radius_km = 10)
    {
        global $wpdb;
        $query = $wpdb->prepare(
            "SELECT *,
            ( 6371 * acos( cos( radians(%s) ) * cos( radians( latitude ) )
            * cos( radians( longitude ) - radians(%s) )
            + sin( radians(%s) ) * sin( radians( latitude ) ) ) ) AS distance
            FROM {$this->table_name}
            HAVING distance < %d
            ORDER BY distance ASC",
            $lat,
            $lng,
            $lat,
            $radius_km
        );

        return $wpdb->get_results($query);
    }

    public function getMSToken(): string
    {
        $client = new Client();
        $response = $client->post('https://rest.membersuite.com/platform/v2/loginUser/36893', [
            'json' => [
                'email' => $_ENV['MS_EMAIL'],
                'password' => $_ENV['MS_PASSWORD'],
            ],
            'headers' => [
                'Accept' => 'application/json'
            ],
        ]);

        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        return $data['data']['idToken'];
    }

    private function executeSearchDirectoryIndividuals(): array
    {
        try {
            $ms_surgeon = get_option('MS_SURGEON');
            $ms_integrated_health = get_option('MS_INTEGRATED_HEALTH');
            $token = $this->getMSToken();

            $this->log('executeSearch: token ' . (empty($token) ? 'EMPTY' : 'ok'));
            $this->log('executeSearch: MS_SURGEON = ' . $ms_surgeon);
            $this->log('executeSearch: MS_INTEGRATED_HEALTH = ' . $ms_integrated_health);

            $response = wp_remote_post('https://rest.membersuite.com/platform/v2/dataSuite/executeSearch', [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode(['msql' => "SELECT ID, FirstName, LastName, Membership.ReceivesMemberBenefits, Practicing__c,
                       Membership.Status.Name, designation FROM Individual
                       WHERE (Membership.ReceivesMemberBenefits = 1
                       AND Membership.Status.Name = 'active'
                       AND (Type = '$ms_surgeon' OR Type = '$ms_integrated_health'))
                       ORDER BY LastName ASC"]),
                'timeout' => 30,
            ]);

            if (is_wp_error($response)) {
                $this->log('executeSearch wp_error: ' . $response->get_error_message());
                return [];
            }

            $status = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            $this->log('executeSearch status: ' . $status);
            $this->log('executeSearch body: ' . substr($body, 0, 200));

            $data = json_decode($body, true);
            $members = $data['data'] ?? [];

            $this->log('executeSearch fetched: ' . count($members) . ' members');

            return $members;

        } catch (\Throwable $e) {
            $this->log('executeSearch exception: ' . $e->getMessage() . ' on line ' . $e->getLine());
            return [];
        }
    }

    private function getMemberGeoData(string $memberId, string $token): array
    {
        $response = wp_remote_get(
            'https://rest.membersuite.com/crm/v1/individuals/' . $memberId,
            [
                'headers' => [
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . $token,
                ],
                'timeout' => 10,
            ]
        );

        if (is_wp_error($response)) {
            $this->log('Geo error for ' . $memberId . ': ' . $response->get_error_message());
            return [];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        // unpack the individual endpoint response json, and package it for the sync function
        return [
            'latitude'      => $data['practice_Address']['geocodeLat']  ?? null,
            'longitude'     => $data['practice_Address']['geocodeLong'] ?? null,
            'city'          => $data['practice_Address']['city']        ?? null,
            'state'         => $data['practice_Address']['state']       ?? null,
            'country'       => $data['practice_Address']['country']     ?? null,
            'practice_line1' => $data['practice_Address']['line1']       ?? null,
            'practice_line2' => $data['practice_Address']['line2']       ?? null,
            'practice_zip'  => $data['practice_Address']['postalCode']  ?? null,
            'practice_phone' => $data['practice_PhoneNumber']            ?? null,
            'email'         => $data['emailAddress']                    ?? null,
            'surgery_types' => !empty($data['surgeryTypes__c']) ? json_encode(array_values(array_filter($data['surgeryTypes__c'], fn ($t) => $t !== 'None Performed'))) : null,
        ];
    }

    private function log(string $message): void
    {
        $log_file = plugin_dir_path(__FILE__) . 'sync.log';
        $timestamp = current_time('Y-m-d H:i:s');
        file_put_contents($log_file, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
    }

    public function ajax_find_members()
    {
        check_ajax_referer('ms_find_members', 'nonce');

        $lat = floatval($_POST['lat'] ?? 0);
        $lng = floatval($_POST['lng'] ?? 0);
        $radius = floatval($_POST['radius'] ?? 25);

        // Convert miles to km for Haversine (1 mile = 1.60934 km)
        $radius_km = $radius * 1.60934;

        $members = $this->get_members_near($lat, $lng, $radius_km);

        // Convert distance from km to miles for display
        foreach ($members as &$member) {
            $member->distance = round($member->distance * 0.621371, 1);
        }

        wp_send_json_success(['members' => $members]);
    }

    public function member_search_shortcode(): string
    {
        $api_key = get_option('ms_google_maps_key') ?: $_ENV['GOOGLE_MAPS_API_KEY'] ?? '';

        ob_start(); ?>

		<div id="ms-member-search">
			<div id="ms-search-form" style="margin-bottom: 20px;">
				<input type="text" id="ms-address-input" placeholder="Enter your address or zip code"
				       style="width: 60%; padding: 8px; font-size: 16px;"/>
				<select id="ms-radius-select" style="padding: 8px; font-size: 16px; margin-left: 10px;">
					<option value="25">25 miles</option>
					<option value="50">50 miles</option>
					<option value="100">100 miles</option>
				</select>
				<button id="ms-search-btn"
				        style="padding: 8px 16px; font-size: 16px; margin-left: 10px; cursor: pointer;">
					Search
				</button>
			</div>

			<div id="ms-search-status" style="margin-bottom: 10px;"></div>

			<div id="ms-map" style="width: 100%; height: 400px; margin-bottom: 20px; display:none;"></div>

			<div id="ms-results"></div>
		</div>

		<script>
			var ms_ajax_url = '<?php echo admin_url('admin-ajax.php'); ?>';
			var ms_nonce = '<?php echo wp_create_nonce('ms_find_members'); ?>';
			var ms_map, ms_markers = [];

			function initMap() {
				ms_map = new google.maps.Map(document.getElementById('ms-map'), {
					zoom: 10,
					center: {
						lat: 39.5,
						lng: -98.35
					} // center of US
				});
			}

			function clearMarkers() {
				ms_markers.forEach(function (marker) {
					marker.setMap(null);
				});
				ms_markers = [];
			}

			document.getElementById('ms-search-btn').addEventListener('click', function () {
				var address = document.getElementById('ms-address-input').value.trim();
				var radius = document.getElementById('ms-radius-select').value;

				if (!address) {
					document.getElementById('ms-search-status').innerText = 'Please enter an address.';
					return;
				}

				document.getElementById('ms-search-status').innerText = 'Searching...';
				// Geocode the address
				var geocoder = new google.maps.Geocoder();
				geocoder.geocode({
					address: address
				}, function (results, status) {
					console.log('geocode status:', status);
					if (status !== 'OK' || !results[0]) {
						document.getElementById('ms-search-status').innerText = 'Address not found. Please try again.';
						return;
					}

					var lat = results[0].geometry.location.lat();
					var lng = results[0].geometry.location.lng();
					console.log('lat:', lat);
					console.log('lng:', lng);
					console.log('full address found:', results[0].formatted_address);

					// Search for nearby members
					fetch(ms_ajax_url, {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded'
						},
						body: new URLSearchParams({
							action: 'ms_find_members',
							nonce: ms_nonce,
							lat: lat,
							lng: lng,
							radius: radius,
						})
					})
						.then(function (r) {
							return r.json();
						})
						.then(function (response) {
							if (!response.success) {
								document.getElementById('ms-search-status').innerText = 'Search failed.';
								return;
							}

							var members = response.data.members;
							var center = new google.maps.LatLng(lat, lng);

							// Update map
							document.getElementById('ms-map').style.display = 'block';
							ms_map.setCenter(center);
							clearMarkers();

							// Add search location marker
							new google.maps.Marker({
								position: center,
								map: ms_map,
								title: 'Your Location',
								icon: 'http://maps.google.com/mapfiles/ms/icons/blue-dot.png'
							});

							// Render results
							var resultsHtml = '';

							if (members.length === 0) {
								resultsHtml = '<p>No members found within ' + radius + ' miles.</p>';
								document.getElementById('ms-search-status').innerText = '';
							} else {
								document.getElementById('ms-search-status').innerText = members.length + ' members found within ' + radius + ' miles.';
								resultsHtml = '<table style="width:100%; border-collapse:collapse;">';
								resultsHtml += '<tr style="background:#f0f0f0;">';
								resultsHtml += '<th style="padding:8px; text-align:left;">Name</th>';
								resultsHtml += '<th style="padding:8px; text-align:left;">Credentials</th>';
								resultsHtml += '<th style="padding:8px; text-align:left;">Distance</th>';
								resultsHtml += '</tr>';

								members.forEach(function (member, i) {
									// Add map marker
									if (member.latitude && member.longitude) {
										var marker = new google.maps.Marker({
											position: {
												lat: parseFloat(member.latitude),
												lng: parseFloat(member.longitude)
											},
											map: ms_map,
											title: member.first_name + ' ' + member.last_name,
										});

										// Info window on click
										var infoWindow = new google.maps.InfoWindow({
											content: '<strong>' + member.first_name + ' ' + member.last_name + '</strong>' +
												'<br>' + (member.designation || '') +
												'<br>' + member.distance + ' miles away'
										});

										marker.addListener('click', function () {
											infoWindow.open(ms_map, marker);
										});

										ms_markers.push(marker);
									}

									var rowBg = (i % 2 === 0) ? '#fff' : '#f9f9f9';
									resultsHtml += '<tr style="background:' + rowBg + ';">';
									resultsHtml += '<td style="padding:8px;">' + member.first_name + ' ' + member.last_name + '</td>';
									resultsHtml += '<td style="padding:8px;">' + (member.designation || '') + '</td>';
									resultsHtml += '<td style="padding:8px;">' + member.distance + ' mi</td>';
									resultsHtml += '</tr>';
								});

								resultsHtml += '</table>';
							}

							document.getElementById('ms-results').innerHTML = resultsHtml;
						});
				});
			});
		</script>

		<script src="https://maps.googleapis.com/maps/api/js?key=<?php echo esc_attr($api_key); ?>&callback=initMap"
		        async
		        defer>
		</script>

		<?php
        return ob_get_clean();
    }
}

new Plugin();
