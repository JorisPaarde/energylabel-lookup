jQuery(document).ready(function($) {
    'use strict';
    
    // Get fresh nonce (cache-proof)
    function getFreshNonce(callback) {
        $.ajax({
            url: ell_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ell_get_nonce'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data && response.data.nonce) {
                    callback(response.data.nonce);
                } else {
                    // Fallback to localized nonce
                    callback(ell_ajax.nonce);
                }
            },
            error: function() {
                // Fallback to localized nonce
                callback(ell_ajax.nonce);
            }
        });
    }
    
    // Form submission handler
    $('#ell-form').on('submit', function(e) {
        e.preventDefault();
        
        // Get form data (without nonce first)
        var formData = {
            action: 'ell_lookup_label',
            postcode: $('#ell-postcode').val().toUpperCase().replace(/\s/g, ''),
            huisnummer: $('#ell-huisnummer').val(),
            toevoeging: $('#ell-toevoeging').val().trim()
        };
        
        // Validate form
        if (!validateForm(formData)) {
            return;
        }
        
        // Show loading state
        showLoading();
        
        // Get fresh nonce first, then make lookup request
        getFreshNonce(function(freshNonce) {
            formData.nonce = freshNonce;
            
            // Make AJAX request
            $.ajax({
                url: ell_ajax.ajax_url,
                type: 'POST',
                data: formData,
                dataType: 'json',
                success: function(response) {
                    hideLoading();
                    
                    if (response.success) {
                        displayResults(response.data);
                    } else {
                        showError(response.data || ell_ajax.error_text);
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    
                    // Check for 403 (nonce/security error)
                    if (xhr.status === 403) {
                        showSecurityError();
                        return;
                    }
                    
                    // Try to parse error response
                    try {
                        var errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.data) {
                            showError(errorResponse.data);
                        } else {
                            showError(ell_ajax.error_text);
                        }
                    } catch (e) {
                        showError(ell_ajax.error_text);
                    }
                }
            });
        });
    });
    
    // Form validation
    function validateForm(data) {
        // Reset previous errors
        $('.ell-form-group').removeClass('ell-error');
        $('.ell-error-message').remove();
        
        var isValid = true;
        
        // Validate postcode
        if (!data.postcode) {
            showFieldError('#ell-postcode', 'Postcode is verplicht.');
            isValid = false;
        } else {
            // Remove spaces and convert to uppercase for validation
            var cleanPostcode = data.postcode.replace(/\s/g, '').toUpperCase();
            
            if (cleanPostcode.length < 6) {
                showFieldError('#ell-postcode', 'Postcode is te kort. Voer 4 cijfers en 2 letters in.');
                isValid = false;
            } else if (cleanPostcode.length > 6) {
                showFieldError('#ell-postcode', 'Postcode is te lang. Voer 4 cijfers en 2 letters in.');
                isValid = false;
            } else if (!/^\d{4}[A-Z]{2}$/.test(cleanPostcode)) {
                // Check if it starts with 4 digits
                if (!/^\d{4}/.test(cleanPostcode)) {
                    showFieldError('#ell-postcode', 'Postcode moet beginnen met 4 cijfers.');
                    isValid = false;
                } else if (!/[A-Z]{2}$/.test(cleanPostcode)) {
                    showFieldError('#ell-postcode', 'Postcode moet eindigen met 2 letters.');
                    isValid = false;
                } else {
                    showFieldError('#ell-postcode', 'Voer een geldige postcode in (bijv. 1234 AB)');
                    isValid = false;
                }
            }
        }
        
        // Validate huisnummer
        if (!data.huisnummer) {
            showFieldError('#ell-huisnummer', 'Huisnummer is verplicht.');
            isValid = false;
        } else if (data.huisnummer < 1) {
            showFieldError('#ell-huisnummer', 'Huisnummer moet groter zijn dan 0.');
            isValid = false;
        } else if (data.huisnummer > 99999) {
            showFieldError('#ell-huisnummer', 'Huisnummer is te groot (maximaal 99999).');
            isValid = false;
        } else if (!Number.isInteger(parseFloat(data.huisnummer))) {
            showFieldError('#ell-huisnummer', 'Huisnummer moet een geheel getal zijn.');
            isValid = false;
        }
        
        return isValid;
    }
    
    // Show field error
    function showFieldError(selector, message) {
        $(selector).closest('.ell-form-group').addClass('ell-error');
        $(selector).after('<span class="ell-error-message">' + message + '</span>');
    }
    
    // Show loading state
    function showLoading() {
        $('#ell-loading').show();
        $('#ell-submit').prop('disabled', true);
    }
    
    // Hide loading state
    function hideLoading() {
        $('#ell-loading').hide();
        $('#ell-submit').prop('disabled', false);
    }
    
    // Display results
    function displayResults(data) {
        var resultsHtml = '<div class="ell-results-content">';
        
        // Status badge
        var statusClass = 'ell-status-active';
        var statusText = data.status_display || 'Actief';
        if (data.is_verlopen) {
            statusClass = 'ell-status-expired';
            statusText = 'Verlopen';
        } else if (data.status_display && (data.status_display.toLowerCase() === 'ingetrokken' || data.status_display.toLowerCase() === 'intrekking')) {
            statusClass = 'ell-status-revoked';
            statusText = 'Ingetrokken';
        }
        
        if (data.energielabel) {
            resultsHtml += '<div class="ell-label-section">';
            resultsHtml += '<div class="ell-label-header">';
            resultsHtml += '<h3>Energielabel Resultaat</h3>';
            resultsHtml += '<span class="ell-status-badge ' + statusClass + '">' + statusText + '</span>';
            resultsHtml += '</div>';
            resultsHtml += '<div class="ell-label-display">';
            
            resultsHtml += '<span class="ell-label-value" data-label="' + data.energielabel + '">' + data.energielabel + '</span>';
            
            if (data.geldig_tot) {
                resultsHtml += '<div class="ell-validity-info">';
                resultsHtml += '<span class="ell-validity-label">Geldig t/m ' + data.geldig_tot + '</span>';
                resultsHtml += '</div>';
            }
            
            resultsHtml += '</div>';
            resultsHtml += '</div>';
        }
        
        // Info cards: Only Type label and Opnamedatum
        resultsHtml += '<div class="ell-info-cards">';
        
        // Type label card
        if (data.is_vereenvoudigd !== undefined) {
            var vereenvoudigdText = data.is_vereenvoudigd ? 'Ja' : 'Nee';
            resultsHtml += '<div class="ell-info-card">';
            resultsHtml += '<div class="ell-info-card-label">Type Label</div>';
            resultsHtml += '<div class="ell-info-card-value">Vereenvoudigd: ' + vereenvoudigdText + '</div>';
            resultsHtml += '</div>';
        }
        
        // Opnamedatum card (fallback to registratiedatum)
        var opnameDatum = data.opnamedatum || data.registratiedatum;
        if (opnameDatum) {
            resultsHtml += '<div class="ell-info-card">';
            resultsHtml += '<div class="ell-info-card-label">Opnamedatum</div>';
            resultsHtml += '<div class="ell-info-card-value">' + opnameDatum + '</div>';
            resultsHtml += '</div>';
        }
        
        resultsHtml += '</div>'; // Close info cards
        
        // Basic info: Only Adres and Gebouw
        resultsHtml += '<div class="ell-overview-grid">';
        
        if (data.adres) {
            resultsHtml += '<div class="ell-overview-item">';
            resultsHtml += '<h4>Adres</h4>';
            resultsHtml += '<p>' + data.adres + '</p>';
            resultsHtml += '</div>';
        }
        
        // Building info with type and year
        if (data.gebouwtype || data.bouwjaar) {
            resultsHtml += '<div class="ell-overview-item">';
            resultsHtml += '<h4>Gebouw</h4>';
            var buildingInfo = '';
            if (data.gebouwtype) {
                buildingInfo += data.gebouwtype;
                if (data.gebouwsubtype) buildingInfo += ' (' + data.gebouwsubtype + ')';
            }
            if (data.bouwjaar) {
                if (buildingInfo) buildingInfo += ' uit ';
                buildingInfo += data.bouwjaar;
            }
            resultsHtml += '<p>' + buildingInfo + '</p>';
            resultsHtml += '</div>';
        }
        
        resultsHtml += '</div>'; // Close overview grid
        
        // Details accordion section - All technical fields
        var hasDetails = data.registratiedatum || data.certificaathouder || data.energieverbruik || data.co2_uitstoot || 
                         data.energieindex || data.energiebehoefte || data.primaire_fossiele_energie || 
                         data.aandeel_hernieuwbare_energie || data.gebruiksoppervlakte || data.compactheid || 
                         data.temperatuuroverschrijding || data.warmtebehoefte || data.gebouwklasse || 
                         data.soort_opname || data.status;
        
        if (hasDetails) {
            resultsHtml += '<div class="ell-details-accordion">';
            resultsHtml += '<button type="button" class="ell-accordion-toggle" onclick="toggleDetailsAccordion(this)">';
            resultsHtml += '<span class="ell-accordion-icon">▼</span>';
            resultsHtml += '<span class="ell-accordion-title">Technische details</span>';
            resultsHtml += '</button>';
            resultsHtml += '<div class="ell-accordion-content" style="display: none;">';
            
            // Basis informatie
            var hasBasicInfo = data.registratiedatum || data.certificaathouder || data.status;
            if (hasBasicInfo) {
                resultsHtml += '<div class="ell-details-group">';
                resultsHtml += '<h4>Basis informatie</h4>';
                resultsHtml += '<div class="ell-details-grid">';
                
                if (data.registratiedatum) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Registratiedatum:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.registratiedatum + '</span>';
                    resultsHtml += '</div>';
                }
                
                if (data.certificaathouder) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Certificaathouder:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.certificaathouder + '</span>';
                    resultsHtml += '</div>';
                }
                
                if (data.status && data.status !== 'Actief') {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Status:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.status + '</span>';
                    resultsHtml += '</div>';
                }
                
                if (data.soort_opname) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Soort opname:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.soort_opname + '</span>';
                    resultsHtml += '</div>';
                }
                
                resultsHtml += '</div>';
                resultsHtml += '</div>';
            }
            
            // Energieverbruik & CO2
            var hasEnergyData = (data.energieverbruik && data.energieverbruik_is_valid) || (data.co2_uitstoot && data.co2_is_valid);
            if (hasEnergyData) {
                resultsHtml += '<div class="ell-details-group">';
                resultsHtml += '<h4>Energieverbruik & CO2</h4>';
                resultsHtml += '<div class="ell-details-grid">';
                
                if (data.energieverbruik && data.energieverbruik_is_valid) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Energieverbruik:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.energieverbruik;
                    if (data.energieverbruik_is_extreme) {
                        resultsHtml += ' <span class="ell-indicative">(Indicatief - EP-Online)</span>';
                    }
                    resultsHtml += '</span>';
                    resultsHtml += '</div>';
                }
                
                if (data.co2_uitstoot && data.co2_is_valid) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">CO2 Uitstoot:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.co2_uitstoot + '</span>';
                    resultsHtml += '</div>';
                }
                
                resultsHtml += '</div>';
                resultsHtml += '</div>';
            }
            
            // NTA 8800 Performance scores
            var hasNTA = data.energieindex || data.energiebehoefte || data.primaire_fossiele_energie || data.aandeel_hernieuwbare_energie;
            if (hasNTA) {
                resultsHtml += '<div class="ell-details-group">';
                resultsHtml += '<h4>Rekenwaarden labelberekening (NTA 8800)</h4>';
                resultsHtml += '<div class="ell-details-grid">';
                
                if (data.energieindex) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Energie-index:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.energieindex + '</span>';
                    resultsHtml += '</div>';
                }
                
                if (data.primaire_fossiele_energie) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Primaire fossiele energie:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.primaire_fossiele_energie + ' <span class="ell-indicative">(Indicatief - EP-Online)</span></span>';
                    resultsHtml += '</div>';
                }
                
                if (data.energiebehoefte) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Energiebehoefte:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.energiebehoefte + '</span>';
                    resultsHtml += '</div>';
                }
                
                if (data.aandeel_hernieuwbare_energie) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Aandeel hernieuwbare energie:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.aandeel_hernieuwbare_energie + ' <span class="ell-indicative">(Indicatief - EP-Online)</span></span>';
                    resultsHtml += '</div>';
                }
                
                resultsHtml += '</div>';
                resultsHtml += '</div>';
            }
            
            // Building characteristics
            var hasBuilding = data.gebruiksoppervlakte || data.compactheid || data.gebouwklasse;
            if (hasBuilding) {
                resultsHtml += '<div class="ell-details-group">';
                resultsHtml += '<h4>Gebouwkenmerken</h4>';
                resultsHtml += '<div class="ell-details-grid">';
                
                if (data.gebruiksoppervlakte) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Gebruiksoppervlakte:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.gebruiksoppervlakte + '</span>';
                    resultsHtml += '</div>';
                }
                
                if (data.compactheid) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Compactheid:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.compactheid + '</span>';
                    resultsHtml += '</div>';
                }
                
                if (data.gebouwklasse) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Gebouwklasse:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.gebouwklasse + '</span>';
                    resultsHtml += '</div>';
                }
                
                resultsHtml += '</div>';
                resultsHtml += '</div>';
            }
            
            // Climate indicators
            if (data.temperatuuroverschrijding || data.warmtebehoefte) {
                resultsHtml += '<div class="ell-details-group">';
                resultsHtml += '<h4>Klimaat-indicatoren</h4>';
                resultsHtml += '<div class="ell-details-grid">';
                
                if (data.temperatuuroverschrijding) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Temperatuuroverschrijding:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.temperatuuroverschrijding + '</span>';
                    resultsHtml += '</div>';
                }
                
                if (data.warmtebehoefte) {
                    resultsHtml += '<div class="ell-detail-item">';
                    resultsHtml += '<span class="ell-detail-label">Warmtebehoefte:</span>';
                    resultsHtml += '<span class="ell-detail-value">' + data.warmtebehoefte + '</span>';
                    resultsHtml += '</div>';
                }
                
                resultsHtml += '</div>';
                resultsHtml += '</div>';
            }
            
            resultsHtml += '</div>'; // Close accordion content
            resultsHtml += '</div>'; // Close accordion
        }
        
        // Advice block (always shown after successful lookup)
        var adviceText = '';
        
        // Priority 1: Label expired
        if (data.is_verlopen || (data.status_display && (data.status_display.toLowerCase() === 'verlopen' || data.status_display.toLowerCase() === 'expired'))) {
            adviceText = 'Dit energielabel is verlopen. Voor verkoop/verhuur heb je meestal een geldig label nodig. Vraag een nieuwe opname aan.';
        }
        // Priority 2: Expires within 6 months
        else if (data.is_near_expiry) {
            adviceText = 'Dit label verloopt binnenkort. Overweeg tijdig een nieuwe opname te plannen.';
        }
        // Priority 3: Simplified label
        else if (data.is_vereenvoudigd || (data.soort_opname && (data.soort_opname.toLowerCase().indexOf('vereenvoudigd') !== -1 || data.soort_opname.toLowerCase().indexOf('basis') !== -1))) {
            adviceText = 'Dit is een vereenvoudigd/basislabel. Voor een nauwkeuriger label en verbeteradvies is een volledige opname aan te raden.';
        }
        // Default
        else {
            adviceText = 'Wil je een beter energielabel? Een adviseur kan verbeterkansen inzichtelijk maken en een nieuwe opname verzorgen.';
        }
        
        if (adviceText) {
            resultsHtml += '<div class="ell-advice-block">';
            resultsHtml += '<h4 class="ell-advice-title">Advies</h4>';
            resultsHtml += '<p class="ell-advice-text">' + adviceText + '</p>';
            resultsHtml += '</div>';
        }
        
        resultsHtml += '<div class="ell-actions">';
        resultsHtml += '<button type="button" id="ell-new-search" class="ell-new-search">Nieuwe Zoekopdracht</button>';
        resultsHtml += '</div>';
        
        resultsHtml += '</div>';
        
        $('#ell-results').html(resultsHtml).show();
    }
    
    // Toggle accordion function
    window.toggleDetailsAccordion = function(button) {
        var accordion = $(button).closest('.ell-details-accordion');
        var content = accordion.find('.ell-accordion-content');
        var icon = accordion.find('.ell-accordion-icon');
        
        if (content.is(':visible')) {
            content.slideUp();
            icon.text('▼');
        } else {
            content.slideDown();
            icon.text('▲');
        }
    }
    
    
    // Show error message
    function showError(message) {
        $('#ell-error').html('<p>' + message + '</p>').show();
    }
    
    // Show security error with refresh option
    function showSecurityError() {
        var errorHtml = '<div class="ell-security-error">';
        errorHtml += '<p>' + (ell_ajax.nonce_error_text || 'Sessie verlopen / beveiligingscontrole mislukt. Ververs de pagina.') + '</p>';
        errorHtml += '<button type="button" class="ell-refresh-btn" onclick="location.reload()">Ververs Pagina</button>';
        errorHtml += '</div>';
        $('#ell-error').html(errorHtml).show();
    }
    
    // Auto-format postcode input
    $('#ell-postcode').on('input', function() {
        var value = $(this).val().toUpperCase().replace(/[^0-9A-Z]/g, '');
        
        if (value.length >= 4) {
            value = value.substring(0, 4) + ' ' + value.substring(4);
        }
        
        $(this).val(value);
    });
    
    // Clear error on input
    $('.ell-form-group input').on('input', function() {
        $(this).closest('.ell-form-group').removeClass('ell-error');
        $(this).siblings('.ell-error-message').remove();
    });

    // Handle new search button click
    $(document).on('click', '#ell-new-search', function() {
        $('#ell-results').hide();
        $('#ell-error').hide();
        $('#ell-form').show();
    });
}); 