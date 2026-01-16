jQuery(document).ready(function($) { 
    if ($('#start-bulk-btn').length === 0) return; // Only run on bulk page

    let isProcessing = false; 
    let shouldStop = false; 

    window.onbeforeunload = function() { 
        if (isProcessing) return "İşlemler devam ediyor. Çıkmak istediğinize emin misiniz?"; 
    }; 

    $('#select-all').change(function() { $('.bulk-item').prop('checked', this.checked); }); 

    $('#stop-bulk-btn').click(function() { 
        shouldStop = true; 
        $(this).text('Durduruluyor...').prop('disabled', true); 
    }); 
     
    $('#start-bulk-btn').click(function() { 
        let items = []; 
        $('.bulk-item:checked').each(function() { 
            let row = $(this).closest('tr'); 
            items.push({ id: $(this).val(), title: row.data('title'), rowId: row.attr('id') }); 
        }); 
         
        if(items.length === 0) { alert('Lütfen en az bir içerik seçin.'); return; } 
        if(!confirm(items.length + ' içerik işlenecek. Başlatılsın mı?')) return; 
         
        isProcessing = true; 
        shouldStop = false; 
        $(this).hide(); 
        $('#stop-bulk-btn').show().text('Durdur').prop('disabled', false); 
        $('#bulk-progress-container').slideDown(); 
        $('#bulk-total').text(items.length); 
         
        processQueue(items, 0); 
    }); 

    function processQueue(items, index) { 
        if (shouldStop) { finishBulk("Kullanıcı tarafından durduruldu."); return; } 
        if (index >= items.length) { finishBulk("Tüm işlemler tamamlandı!"); return; } 

        let percent = Math.round((index / items.length) * 100); 
        $('#bulk-current').text(index + 1); 
        $('#bulk-percent-text').text(percent + '%'); 
        $('#bulk-overall-bar').css('width', percent + '%'); 

        let item = items[index]; 
        let row = $('#' + item.rowId); 
        let rowStatus = row.find('.wasw-bulk-status'); 
        let createImg = $('#bulk_create_img').is(':checked'); 
        let createShort = $('#bulk_create_short').is(':checked'); 
         
        $('html, body').animate({ scrollTop: row.offset().top - 150 }, 500); 
         
        rowStatus.text('Analiz Ediliyor...').removeClass('wasw-error').addClass('wasw-processing'); 
         
        // Adım 1: Giriş 
        $.ajax({ 
            url: wasw_vars.ajax_url, type: 'POST', 
            data: { action: 'wasw_generate_chain', step: 'phase_1', product_id: item.id, title: item.title, create_image: createImg, create_short: createShort, nonce: wasw_vars.nonce }, 
            success: function(res1) { 
                if(!res1.success) {  
                    rowStatus.text('Hata (Adım 1): ' + (res1.data || 'Bilinmeyen')).addClass('wasw-error');  
                    setTimeout(() => processQueue(items, index+1), 1000); // Hata olsa da geç 
                    return;  
                } 
                 
                rowStatus.text('Teknik Detaylar (Adım 2)...'); 
                let content = res1.data.content; 
                 
                // Adım 2: Teknik 
                $.ajax({ 
                    url: wasw_vars.ajax_url, type: 'POST', 
                    data: { action: 'wasw_generate_chain', step: 'phase_2', product_id: item.id, title: item.title, nonce: wasw_vars.nonce }, 
                    success: function(res2) { 
                        if(!res2.success) {  
                            rowStatus.text('Hata (Adım 2)').addClass('wasw-error');  
                            setTimeout(() => processQueue(items, index+1), 1000);  
                            return;  
                        } 
                        content += "\n\n" + res2.data.content; 
                         
                        rowStatus.text('SEO ve Sonuç (Adım 3)...'); 
                         
                        // Adım 3: Sonuç 
                        $.ajax({ 
                            url: wasw_vars.ajax_url, type: 'POST', 
                            data: { action: 'wasw_generate_chain', step: 'phase_3', product_id: item.id, title: item.title, create_short: createShort, nonce: wasw_vars.nonce }, 
                            success: function(res3) { 
                                if(!res3.success) {  
                                    rowStatus.text('Hata (Adım 3)').addClass('wasw-error');  
                                    setTimeout(() => processQueue(items, index+1), 1000);  
                                    return;  
                                } 
                                content += "\n\n" + res3.data.content; 
                                 
                                rowStatus.text('Kaydediliyor...'); 

                                // Kaydet 
                                $.ajax({ 
                                    url: wasw_vars.ajax_url, type: 'POST', 
                                    data: { action: 'wasw_save_final', product_id: item.id, title: item.title, content: content, nonce: wasw_vars.nonce }, 
                                    success: function(finalRes) { 
                                        rowStatus.text('Tamamlandı!').removeClass('wasw-processing').addClass('wasw-done'); 
                                        setTimeout(function() { processQueue(items, index+1); }, 1000); 
                                    }, 
                                    error: function() {  
                                        rowStatus.text('Kayıt Hatası').addClass('wasw-error');  
                                        setTimeout(() => processQueue(items, index+1), 1000);  
                                    } 
                                }); 
                            }, 
                            error: function() { 
                                rowStatus.text('Bağlantı Hatası (Adım 3)').addClass('wasw-error'); 
                                setTimeout(() => processQueue(items, index+1), 1000); 
                            } 
                        }); 
                    }, 
                    error: function() { 
                        rowStatus.text('Bağlantı Hatası (Adım 2)').addClass('wasw-error'); 
                        setTimeout(() => processQueue(items, index+1), 1000); 
                    } 
                }); 
            }, 
            error: function() {  
                rowStatus.text('Bağlantı Hatası (Adım 1)').addClass('wasw-error');  
                setTimeout(() => processQueue(items, index+1), 1000);  
            } 
        }); 
    } 

    function finishBulk(msg) { 
        isProcessing = false; 
        alert(msg); 
        $('#start-bulk-btn').show(); 
        $('#stop-bulk-btn').hide(); 
        $('#bulk-percent-text').text('100%'); 
        $('#bulk-overall-bar').css('width', '100%'); 
    } 
});