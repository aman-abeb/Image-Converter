<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    <div id="ic-message"></div>

    <form id="ic-form">
        <div class="form-group">
            <label for="target_format">Convert to:</label>
            <select id="target_format" required>
                <?php foreach ($supported_formats as $format => $name): ?>
                    <option value="<?php echo esc_attr($format); ?>">
                        <?php echo esc_html($name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <br>
        <button type="button" id="ic-select" class="button button-secondary">
            <?php esc_html_e('Select Images', 'image-converter'); ?>
        </button>

        <button type="button" id="ic-convert" class="button button-primary">
            <?php esc_html_e('Convert Images', 'image-converter'); ?>
        </button>
    </form>

    <div id="ic-progress" style="margin-top:20px;"></div>
</div>