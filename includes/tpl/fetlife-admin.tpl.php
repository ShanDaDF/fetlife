<div id="fetlife-form" class="wrap">
    <h2>Fetlife settings</h2>
    <p><?php print __('Settings to connect to fetlife and gather information to display on the site. On submit, the password is encrypted in the database.'); ?></p>
    <form method="post" action="options.php" enctype="multipart/form-data">
        <?php settings_errors();?>
        <?php settings_fields('fetlife_settings'); ?>  
        <?php do_settings_sections('fetlife'); ?> 
        <p class="submit">
            <input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes'); ?>" />  
        </p>
    </form>
    <h3>Available shortcodes</h3>
    <br/>
    <strong>Events</strong>
    <p>
        [fetlife_events] - optional parameter: organiser_id <em>(a single ID, or a comma separated value list of fetlife user IDs - default value is "Fetlife events organisers' IDs" above).</em>
    </p>
    <h3>Automatic refresh</h3>
    <p>The fetlife data is refreshed everyday using a cron recurring task.<br/>
        This can be a long task so it is recommended to execute it when the impact on the servers (both the website's and fetlife's) is limited.
    </p>
    <h3>Manual data refresh (use carefully)</h3>
    <p>
        You can manually refresh the data by clicking "Refresh fetlife data" in the admin bar to force update the fetlife data.
    </p>
</div>


