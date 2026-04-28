<?php

namespace MemberSuiteGeoSearch;

class Settings
{
    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    // Add a menu item under "Settings"
    public function add_settings_page()
    {
        add_options_page(
            'MS Geosearch Plugin Settings',  // Page title
            'MS Geosearch',        // Menu label
            'manage_options',      // Capability required
            'ms-plugin-settings',  // Menu slug
            [$this, 'render_page']
        );
    }

    public function register_settings()
    {
        register_setting('ms_plugin_group', 'MS_SURGEON');
        register_setting('ms_plugin_group', 'MS_SURGEON_RENEWAL');
        register_setting('ms_plugin_group', 'MS_INTEGRATED_HEALTH');
        register_setting('ms_plugin_group', 'MS_INTEGRATED_HEALTH_RENEWAL');
        register_setting('ms_plugin_group', 'MS_INTERNATIONAL');
        register_setting('ms_plugin_group', 'MS_INTERNATIONAL_RENEWAL');
    }

    public function render_page()
    { ?>
        <div class="wrap">
            <h1>MS Geosearch Plugin Settings</h1>
            <form method="post" action="options.php">
                <?php settings_fields('ms_plugin_group'); ?>
                <p>Here are the values that will be sent to the Membersuite API to identify Regular Surgeon or
                    Integrated Health members.<br />
                    If the GUIDs for these member types change on Membersuite, they will have to be updated here.
                </p>
				<table class="form-table">
					<tr>
						<th>MS_SURGEON</th>
						<td>
							<input type="text" name="MS_SURGEON"
				                   value="<?php echo esc_attr(get_option('MS_SURGEON')); ?>"
				                   class="regular-text" />
						</td>
					</tr>
					<tr>
						<th>MS_SURGEON_RENEWAL</th>
						<td>
							<input type="text" name="MS_SURGEON_RENEWAL"
				                   value="<?php echo esc_attr(get_option('MS_SURGEON_RENEWAL')); ?>"
				                   class="regular-text" />
						</td>
					</tr>
					<tr>
						<th>MS_INTEGRATED_HEALTH</th>
						<td>
							<input type="text" name="MS_INTEGRATED_HEALTH"
				                   value="<?php echo esc_attr(get_option('MS_INTEGRATED_HEALTH')); ?>"
				                   class="regular-text" />
						</td>
					</tr>
					<tr>
						<th>MS_INTEGRATED_HEALTH_RENEWAL</th>
						<td>
							<input type="text" name="MS_INTEGRATED_HEALTH_RENEWAL"
				                   value="<?php echo esc_attr(get_option('MS_INTEGRATED_HEALTH_RENEWAL')); ?>"
				                   class="regular-text" />
						</td>
					</tr>
					<tr>
						<th>MS_INTERNATIONAL</th>
						<td>
							<input type="text" name="MS_INTERNATIONAL"
				                   value="<?php echo esc_attr(get_option('MS_INTERNATIONAL')); ?>"
				                   class="regular-text" />
						</td>
					</tr>
					<tr>
						<th>MS_INTERNATIONAL_RENEWAL</th>
						<td>
							<input type="text" name="MS_INTERNATIONAL_RENEWAL"
				                   value="<?php echo esc_attr(get_option('MS_INTERNATIONAL_RENEWAL')); ?>"
				                   class="regular-text" />
						</td>
					</tr>
				</table>
                <?php submit_button(); ?>
            </form>

            <!-- Separate form for the sync button -->
            <hr>
            <h2>Sync Members</h2>

            <div id="ms-sync-progress" style="display:none; margin-top:20px;">
                <div style="background:#f0f0f0; border-radius:4px; height:20px; width:100%;">
                    <div id="ms-sync-bar"
                        style="background:#2271b1; height:20px; border-radius:4px; width:0%; transition:width 0.3s;"></div>
                </div>
                <p id="ms-sync-status">Starting sync...</p>
            </div>

            <script>
                jQuery(function($) {
                    $('#ms-sync-start').on('click', function(e) {
                        e.preventDefault();
                        $('#ms-sync-progress').show();
                        runBatch(0);
                    });

                    function runBatch(offset, retries = 3) {
                        $.post(ajaxurl, {
                            action: 'ms_sync_batch',
                            nonce: '<?php echo wp_create_nonce("ms_sync_batch"); ?>',
                            offset: offset
                        }, function(response) {
                            if (!response.success) {
                                $('#ms-sync-status').text('Error during sync.');
                                return;
                            }

                            var data = response.data;
                            var percentage = Math.round((data.processed / data.total) * 100);

                            $('#ms-sync-bar').css('width', percentage + '%');
                            $('#ms-sync-status').text(data.processed + ' of ' + data.total + ' members synced...');

                            if (data.done) {
                                $('#ms-sync-status').text('Sync complete! ' + data.total + ' members synced.');
                            } else {
                                runBatch(data.processed, 3);
                            }
                        }).fail(function(xhr, status, error) {
                            if (retries > 0) {
                                $('#ms-sync-status').text('Timeout, retrying batch at offset ' + offset + '...');
                                setTimeout(function() {
                                    runBatch(offset, retries - 1);
                                }, 3000);
                            } else {
                                $('#ms-sync-status').text('Failed at offset ' + offset + ' after 3 retries.');
                            }
                        });
                    }
                });
            </script>

            <button id="ms-sync-start" class="button button-secondary">Sync Now</button>

        </div>

<?php }  // end render_page
}
