jQuery(document).ready(function($) { 
    // Toggle Password Visibility
    $(document).on('click', '.wasw-toggle-password', function(e) {
        e.preventDefault();
        let btn = $(this);
        let input = btn.prev('input');
        let icon = btn.find('.dashicons');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('dashicons-visibility').addClass('dashicons-hidden');
        } else {
            input.attr('type', 'password');
            icon.removeClass('dashicons-hidden').addClass('dashicons-visibility');
        }
    });

    if ($('#wasw-start-btn').length === 0) return;

    // PDF Yükleme - WordPress Media Library
    $('#wasw_pdf_upload').click(function(e) {
        e.preventDefault();
        
        var frame = wp.media({
            title: 'Teknik PDF Seç',
            button: { text: 'PDF Kullan' },
            multiple: false,
            library: { type: 'application/pdf' }
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#wasw_pdf_attachment_id').val(attachment.id);
            $('#wasw_pdf_url').val('');
            $('#wasw-pdf-name').text('📎 ' + attachment.filename);
            $('#wasw-pdf-preview').slideDown();
        });
        
        frame.open();
    });

    // PDF URL girişi
    $('#wasw_pdf_url').on('change blur', function() {
        var url = $(this).val().trim();
        if(url && url.toLowerCase().endsWith('.pdf')) {
            $('#wasw_pdf_attachment_id').val('');
            var filename = url.split('/').pop().split('?')[0];
            $('#wasw-pdf-name').text('🔗 ' + filename);
            $('#wasw-pdf-preview').slideDown();
        } else if(!url) {
            if(!$('#wasw_pdf_attachment_id').val()) {
                $('#wasw-pdf-preview').slideUp();
            }
        }
    });

    // PDF kaldır
    $('#wasw-pdf-remove').click(function(e) {
        e.preventDefault();
        $('#wasw_pdf_url').val('');
        $('#wasw_pdf_attachment_id').val('');
        $('#wasw-pdf-preview').slideUp();
    });

    let accumulatedContent = ""; 
    $('#wasw-start-btn').click(function(e) { 
        e.preventDefault(); 
        let pid = $('#post_ID').val(); 
        let title = $('#title').val(); 
        let img = $('#wasw_img_toggle').is(':checked'); 
        let shortDesc = $('#wasw_short_desc_toggle').is(':checked');
        let pdfUrl = $('#wasw_pdf_url').val().trim();
        let pdfAttachmentId = $('#wasw_pdf_attachment_id').val();
        
        if(!title) { alert('Lütfen başlık girin!'); return; } 
        $(this).prop('disabled', true).css('opacity', '0.7'); 
        $('#wasw-status-area').slideDown(); $('#wasw-preview-area').slideUp(); 
        
        // PDF varsa özel mesaj
        if(pdfUrl || pdfAttachmentId) {
            updateProgress(3, 'PDF okunuyor...');
        } else {
            updateProgress(5, 'Analiz...');
        }
        
        accumulatedContent = ""; 
        $.ajax({ 
            url: wasw_vars.ajax_url, type: 'POST', 
            data: { 
                action: 'wasw_generate_chain', 
                step: 'phase_1', 
                product_id: pid, 
                title: title, 
                create_image: img, 
                create_short: shortDesc, 
                pdf_url: pdfUrl,
                pdf_attachment_id: pdfAttachmentId,
                nonce: wasw_vars.nonce 
            }, 
            success: function(res) { 
                if(res.success) { 
                    accumulatedContent += res.data.content; 
                    updateProgress(35, 'Görsel Tamam.'); 
                    if(res.data.image && res.data.image.url) { 
                        $('#wasw-preview-img').attr('src', res.data.image.url); 
                        $('#wasw_temp_img_id').val(res.data.image.id); 
                        $('#wasw-preview-area').slideDown(); 
                    } 
                    $.ajax({ 
                        url: wasw_vars.ajax_url, type: 'POST', 
                        data: { action: 'wasw_generate_chain', step: 'phase_2', product_id: pid, title: title, nonce: wasw_vars.nonce }, 
                        success: function(res2) { 
                            if(res2.success) { 
                                accumulatedContent += "\n\n" + res2.data.content; 
                                updateProgress(70, 'Teknik Veriler...'); 
                                $.ajax({ 
                                    url: wasw_vars.ajax_url, type: 'POST', 
                                    data: { action: 'wasw_generate_chain', step: 'phase_3', product_id: pid, title: title, create_short: shortDesc, nonce: wasw_vars.nonce }, 
                                    success: function(res3) { 
                                        if(res3.success) { 
                                            accumulatedContent += "\n\n" + res3.data.content; 
                                            updateProgress(90, 'Rank Math...'); 
                                            $.ajax({ 
                                                url: wasw_vars.ajax_url, type: 'POST', 
                                                data: { action: 'wasw_save_final', product_id: pid, title: title, content: accumulatedContent, nonce: wasw_vars.nonce }, 
                                                success: function(finalRes) { 
                                                    updateProgress(100, 'Tamamlandı!'); 
                                                    $('#wasw-start-btn').prop('disabled', false).text('✨ Yeniden').css('opacity', '1'); 
                                                    var pdfMsg = (pdfUrl || pdfAttachmentId) ? ' (PDF referansı kullanıldı)' : '';
                                                    $('#wasw-msg').html('✅ Başarılı!' + pdfMsg); 
                                                    if(typeof tinymce !== 'undefined' && tinymce.get('content')) tinymce.get('content').setContent(accumulatedContent); 
                                                    if(finalRes.data.seo_keyword) $('input[name="rank_math_focus_keyword"]').val(finalRes.data.seo_keyword); 
                                                    setTimeout(function(){ location.reload(); }, 1500); 
                                                } 
                                            }); 
                                        } 
                                    } 
                                }); 
                            } 
                        } 
                    }); 
                } else { handleError(res.data); } 
            }, 
            error: function() { handleError('Hata.'); } 
        }); 
    }); 
    
    $('#wasw-approve-img').click(function(e) { 
        e.preventDefault(); 
        let btn = $(this); 
        btn.text('Onaylanıyor...'); 
        $.ajax({ 
            url: wasw_vars.ajax_url, type: 'POST', 
            data: { action: 'wasw_approve_img', product_id: $('#post_ID').val(), image_id: $('#wasw_temp_img_id').val(), nonce: wasw_vars.nonce }, 
            success: function() { btn.text('Onaylandı!').prop('disabled',true); } 
        }); 
    }); 

    function updateProgress(percent, text) { $('#wasw-progress-bar').css('width', percent + '%'); $('#wasw-percent').text(percent + '%'); $('#wasw-step-text').text(text); } 
    function handleError(msg) { $('#wasw-msg').html('❌ '+msg); $('#wasw-start-btn').prop('disabled', false).css('opacity', '1'); } 
});