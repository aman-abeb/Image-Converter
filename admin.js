jQuery(function($) {
    let frame, selectedIds = [];

    // Open media library
    $('#ic-select').click(function(e) {
        e.preventDefault();
        const format = $('#target_format').val();
        
        frame = wp.media({
            title: 'Select Images',
            library: {
                type: ic_vars.formats[format]
            },
            multiple: true
        });

        frame.on('select', function() {
            selectedIds = frame.state().get('selection').map(att => att.id);
            $('#ic-progress').html(`Selected ${selectedIds.length} images.`);
        });

        frame.open();
    });

    // Convert images
    $('#ic-convert').click(function(e) {
        e.preventDefault();
        if (!selectedIds.length) return alert('Please select images first.');

        $('#ic-progress').html('Converting... <div class="spinner is-active"></div>');
        
        $.post(ic_vars.ajax_url, {
            action: 'ic_convert_images',
            nonce: ic_vars.nonce,
            target_format: $('#target_format').val(),
            attachment_ids: selectedIds
        }).done(response => {
            $('#ic-progress').html(
                response.success ? 
                '<div class="notice notice-success"><p>Conversion successful!</p></div>' :
                `<div class="notice notice-error"><p>Error: ${response.data}</p></div>`
            );
            selectedIds = [];
        }).fail(() => {
            $('#ic-progress').html(
                '<div class="notice notice-error"><p>Conversion failed. Please try again.</p></div>'
            );
        });
    });
});