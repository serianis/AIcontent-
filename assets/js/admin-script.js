(function ($) {
    function setLoading($btn, isLoading) {
        $btn.prop('disabled', isLoading);
        $btn.toggleClass('is-busy', isLoading);
    }

    function renderError(message) {
        return '<div class="notice notice-error"><p>' + _.escape(message) + '</p></div>';
    }

    function renderSuccess(message) {
        return '<div class="notice notice-success"><p>' + _.escape(message) + '</p></div>';
    }

    function showNotification(message, type) {
        type = type || 'info';
        var $container = $('.smartcontentai-wrap');
        if ($container.length === 0) {
            $container = $('body');
        }
        
        var notificationClass = 'notice notice-' + type;
        var $notification = $('<div class="' + notificationClass + ' is-dismissible"><p>' + _.escape(message) + '</p></div>');
        
        // Remove existing notifications
        $container.find('.smartcontentai-notification').remove();
        
        // Add new notification
        $notification.addClass('smartcontentai-notification');
        $container.prepend($notification);
        
        // Auto-dismiss after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
        
        // Make dismissible
        $notification.on('click', '.notice-dismiss', function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        });
    }

    $(function () {
        // Generator Page Functionality
        var $previewBtn = $('#smartcontentai-preview-btn');
        var $generateBtn = $('#smartcontentai-generate-btn');
        var $topic = $('#smartcontentai-topic');
        var $keyword = $('#smartcontentai-keyword');
        var $mode = $('#smartcontentai-publish-mode');
        var $scheduledAt = $('#smartcontentai-scheduled-at');
        var $result = $('#smartcontentai-generator-result');
        var $preview = $('#smartcontentai-preview');

        function getPayload() {
            return {
                topic: $topic.val() || '',
                keyword: $keyword.val() || '',
                publish_mode: $mode.length ? $mode.val() : 'draft',
                scheduled_at: $scheduledAt.length ? $scheduledAt.val() : ''
            };
        }

        if ($previewBtn.length) {
            $previewBtn.on('click', function (e) {
                e.preventDefault();

                var payload = getPayload();
                if (!payload.topic.trim()) {
                    $result.html(renderError(SmartContentAIAdmin.i18n.missingTopic));
                    return;
                }

                setLoading($previewBtn, true);
                $result.html('');
                $preview.html('');

                $.post(SmartContentAIAdmin.ajaxUrl, {
                    action: 'smartcontentai_preview_post',
                    nonce: SmartContentAIAdmin.nonces.preview,
                    topic: payload.topic,
                    keyword: payload.keyword
                })
                    .done(function (response) {
                        if (!response || !response.success) {
                            $result.html(renderError((response && response.data) ? response.data : SmartContentAIAdmin.i18n.unknownError));
                            return;
                        }
                        $result.html(renderSuccess(SmartContentAIAdmin.i18n.previewReady));
                        $preview.html(response.data.preview_html || '');
                    })
                    .fail(function () {
                        $result.html(renderError(SmartContentAIAdmin.i18n.unknownError));
                    })
                    .always(function () {
                        setLoading($previewBtn, false);
                    });
            });
        }

        if ($generateBtn.length) {
            $generateBtn.on('click', function (e) {
                e.preventDefault();

                var payload = getPayload();
                if (!payload.topic.trim()) {
                    $result.html(renderError(SmartContentAIAdmin.i18n.missingTopic));
                    return;
                }

                setLoading($generateBtn, true);
                $result.html(renderSuccess(SmartContentAIAdmin.i18n.generating));

                $.post(SmartContentAIAdmin.ajaxUrl, {
                    action: 'smartcontentai_generate',
                    nonce: SmartContentAIAdmin.nonces.generate,
                    topic: payload.topic,
                    keyword: payload.keyword,
                    publish_mode: payload.publish_mode,
                    scheduled_at: payload.scheduled_at
                })
                    .done(function (response) {
                        if (!response || !response.success) {
                            $result.html(renderError((response && response.data) ? response.data : SmartContentAIAdmin.i18n.unknownError));
                            return;
                        }

                        var link = response.data && response.data.link ? response.data.link : '';
                        var msg = SmartContentAIAdmin.i18n.created;
                        if (link) {
                            msg += ' <a href="' + link + '" target="_blank" rel="noopener">' + _.escape(SmartContentAIAdmin.i18n.viewPost) + '</a>';
                        }
                        $result.html('<div class="notice notice-success"><p>' + msg + '</p></div>');
                    })
                    .fail(function () {
                        $result.html(renderError(SmartContentAIAdmin.i18n.unknownError));
                    })
                    .always(function () {
                        setLoading($generateBtn, false);
                    });
            });
        }

        if ($mode.length && $scheduledAt.length) {
            function toggleScheduled() {
                var isScheduled = $mode.val() === 'scheduled';
                $scheduledAt.closest('tr').toggle(isScheduled);
            }
            $mode.on('change', toggleScheduled);
            toggleScheduled();
        }

        // Settings Page Functionality
        // Tab switching
        $('.smartcontentai-tabs .nav-tab').on('click', function (e) {
            e.preventDefault();
            
            var target = $(this).attr('href').substring(1);
            
            $('.smartcontentai-tabs .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            $('.smartcontentai-tabs .tab-content').removeClass('active');
            $('#' + target).addClass('active');
            
            // Load models when Models tab is opened
            if (target === 'models') {
                loadModels();
            }
            
            // Load custom providers when Custom Providers tab is opened
            if (target === 'custom-providers') {
                loadCustomProviders();
            }
        });
        
        // Routing mode change handler
        $('#smartcontentai_routing_mode').on('change', function() {
            var mode = $(this).val();
            var $manualSelection = $('#manual-model-selection');
            var $fixedModelRow = $('#smartcontentai_fixed_model').closest('tr');
            
            if (mode === 'manual') {
                $manualSelection.show();
                $fixedModelRow.hide();
                // Load available models for selection
                loadModelOptions();
            } else if (mode === 'fixed') {
                $manualSelection.hide();
                $fixedModelRow.show();
                // Load available models for fixed selection
                loadFixedModelOptions();
            } else {
                $manualSelection.hide();
                $fixedModelRow.hide();
            }
        });
        
        // Load model options on page load if in manual mode
        var currentRoutingMode = $('#smartcontentai_routing_mode').val();
        if (currentRoutingMode === 'manual') {
            $('#manual-model-selection').show();
            $('#smartcontentai_fixed_model').closest('tr').hide();
            loadModelOptions();
        } else if (currentRoutingMode === 'fixed') {
            $('#manual-model-selection').hide();
            $('#smartcontentai_fixed_model').closest('tr').show();
            loadFixedModelOptions();
        } else {
            $('#manual-model-selection').hide();
            $('#smartcontentai_fixed_model').closest('tr').hide();
        }
        
        function loadModelOptions() {
            $.ajax({
                url: SmartContentAIAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smartcontentai_load_models',
                    nonce: SmartContentAIAdmin.nonces.loadModels || ''
                },
                success: function(response) {
                    if (response.success) {
                        populateModelSelects(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Load Models Error:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
        }
        
        function loadFixedModelOptions() {
            $.ajax({
                url: SmartContentAIAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smartcontentai_load_models',
                    nonce: SmartContentAIAdmin.nonces.loadModels || ''
                },
                success: function(response) {
                    if (response.success) {
                        populateFixedModelSelect(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Load Fixed Models Error:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
        }
        
        function populateModelSelects(models) {
            var $cheapSelect = $('#smartcontentai_cheap_model');
            var $standardSelect = $('#smartcontentai_standard_model');
            var $premiumSelect = $('#smartcontentai_premium_model');
            
            // Store current selections
            var currentCheap = $cheapSelect.val();
            var currentStandard = $standardSelect.val();
            var currentPremium = $premiumSelect.val();
            
            // Clear existing options (except the first "auto-select" option)
            $cheapSelect.find('option:not(:first)').remove();
            $standardSelect.find('option:not(:first)').remove();
            $premiumSelect.find('option:not(:first)').remove();
            
            // Group models by tier and populate selects
            $.each(models, function(slug, model) {
                if (!model.available) return;
                
                var option = '<option value="' + slug + '">' + model.name + ' (' + model.provider_name + ')</option>';
                
                if (model.tier === 'cheap') {
                    $cheapSelect.append(option);
                } else if (model.tier === 'standard') {
                    $standardSelect.append(option);
                } else if (model.tier === 'premium') {
                    $premiumSelect.append(option);
                }
            });
            
            // Restore previous selections
            if (currentCheap) $cheapSelect.val(currentCheap);
            if (currentStandard) $standardSelect.val(currentStandard);
            if (currentPremium) $premiumSelect.val(currentPremium);
        }
        
        function populateFixedModelSelect(models) {
            var $fixedSelect = $('#smartcontentai_fixed_model');
            
            // Store current selection
            var currentFixed = $fixedSelect.val();
            
            // Clear existing options (except the first placeholder)
            $fixedSelect.find('option:not(:first)').remove();
            
            // Add all available models
            $.each(models, function(slug, model) {
                if (!model.available) return;
                
                var option = '<option value="' + slug + '">' + model.name + ' (' + model.provider_name + ') - ' + model.tier + '</option>';
                $fixedSelect.append(option);
            });
            
            // Restore previous selection
            if (currentFixed) $fixedSelect.val(currentFixed);
        }
        
        function loadModels() {
            $.ajax({
                url: SmartContentAIAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smartcontentai_load_models',
                    nonce: SmartContentAIAdmin.nonces.loadModels || ''
                },
                success: function(response) {
                    if (response.success) {
                        displayModels(response.data);
                    } else {
                        $('#models-list').html('<p>Error loading models.</p>');
                    }
                },
                error: function() {
                    $('#models-list').html('<p>Error loading models.</p>');
                }
            });
        }
        
        function displayModels(models) {
            var html = '<div class="models-grid">';
            
            $.each(models, function(slug, model) {
                var statusClass = model.available ? 'available' : 'unavailable';
                var statusText = model.available ? 'Available' : 'No API Key';
                
                html += '<div class="model-card ' + statusClass + '">';
                html += '<h4>' + model.name + '</h4>';
                html += '<p><strong>Provider:</strong> ' + model.provider_name + '</p>';
                html += '<p><strong>Tier:</strong> ' + model.tier + '</p>';
                html += '<p><strong>Max Tokens:</strong> ' + model.max_tokens + '</p>';
                html += '<p><strong>Cost/1K:</strong> ' + model.cost_per_1k + '</p>';
                html += '<p class="status ' + statusClass + '">' + statusText + '</p>';
                html += '</div>';
            });
            
            html += '</div>';
            $('#models-list').html(html);
        }
        
        // Custom Providers JavaScript
        var currentProviderId = null;
        var editingProvider = false;
        
        function loadCustomProviders() {
            $.ajax({
                url: SmartContentAIAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smartcontentai_load_custom_providers',
                    nonce: SmartContentAIAdmin.nonces.loadCustomProviders || ''
                },
                success: function(response) {
                    if (response.success) {
                        displayCustomProviders(response.data);
                    } else {
                        $('#custom-providers-list').html('<p>Error loading custom providers.</p>');
                    }
                },
                error: function() {
                    $('#custom-providers-list').html('<p>Error loading custom providers.</p>');
                }
            });
        }
        
        function displayCustomProviders(providers) {
            if (providers.length === 0) {
                $('#custom-providers-list').html('<p>No custom providers configured.</p>');
                return;
            }
            
            var html = '<div class="custom-providers-grid">';
            
            $.each(providers, function(index, provider) {
                var statusClass = provider.enabled ? 'enabled' : 'disabled';
                var statusText = provider.enabled ? 'Enabled' : 'Disabled';
                
                html += '<div class="provider-card ' + statusClass + '">';
                html += '<h4>' + provider.name + '</h4>';
                html += '<p><strong>Base URL:</strong> ' + provider.base_url + '</p>';
                html += '<p><strong>Auth Type:</strong> ' + provider.auth_type + '</p>';
                html += '<p><strong>Models:</strong> ' + provider.models_count + '</p>';
                html += '<p class="status ' + statusClass + '">' + statusText + '</p>';
                html += '<div class="provider-actions">';
                html += '<button type="button" class="button button-small" onclick="editProvider(' + provider.id + ')">Edit</button>';
                html += '<button type="button" class="button button-small" onclick="manageModels(' + provider.id + ', \'' + provider.name + '\')">Models</button>';
                html += '<button type="button" class="button button-small" onclick="testProvider(' + provider.id + ')">Test</button>';
                html += '<button type="button" class="button button-small button-link-delete" onclick="deleteProvider(' + provider.id + ')">Delete</button>';
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            $('#custom-providers-list').html(html);
        }
        
        // Provider form functions
        $('#provider_name').on('input', function() {
            if (!editingProvider) {
                var name = $(this).val();
                var slug = name.toLowerCase().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
                $('#provider_slug').val(slug);
            }
        });
        
        $('#test-provider-btn').on('click', function() {
            testCurrentProvider();
        });
        
        $('#cancel-edit-btn').on('click', function() {
            resetProviderForm();
        });
        
        $('#cancel-model-btn').on('click', function() {
            resetModelForm();
        });
        
        function testCurrentProvider() {
            var formData = getProviderFormData();
            
            if (!formData.name || !formData.base_url) {
                var errorMsg = !formData.name && !formData.base_url ? 
                    'Provider name and base URL are required.' :
                    (!formData.name ? 'Provider name is required.' : 'Base URL is required.');
                showNotification(errorMsg, 'error');
                return;
            }
            
            $('#test-provider-btn').prop('disabled', true).text('Testing...');
            
            // Temporarily save provider for testing
            var tempProviderId = saveTempProvider(formData);
            
            $.ajax({
                url: SmartContentAIAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smartcontentai_test_custom_provider',
                    provider_id: tempProviderId,
                    nonce: SmartContentAIAdmin.nonces.testProvider || ''
                },
                success: function(response) {
                    $('#test-provider-btn').prop('disabled', false).text('Test Connection');
                    
                    if (response.success) {
                        showNotification('Success: ' + response.data, 'success');
                    } else {
                        showNotification('Error: ' + response.data, 'error');
                    }
                    
                    // Clean up temp provider
                    deleteTempProvider(tempProviderId);
                },
                error: function() {
                    $('#test-provider-btn').prop('disabled', false).text('Test Connection');
                    showNotification('Connection test failed. Please check your configuration.', 'error');
                    deleteTempProvider(tempProviderId);
                }
            });
        }
        
        function getProviderFormData() {
            return {
                id: $('#provider_id').val() || null,
                name: $('#provider_name').val(),
                slug: $('#provider_slug').val(),
                base_url: $('#base_url').val(),
                auth_type: $('#auth_type').val(),
                api_key: $('#api_key').val(),
                custom_headers: $('#custom_headers').val(),
                enabled: $('#enabled').is(':checked') ? 1 : 0,
                is_default: $('#is_default').is(':checked') ? 1 : 0
            };
        }
        
        function resetProviderForm() {
            $('#custom-provider-form')[0].reset();
            $('#provider_id').val('');
            $('#provider-form-title').text('Add New Provider');
            $('#save-provider-btn').text('Save Provider');
            $('#cancel-edit-btn').hide();
            editingProvider = false;
            currentProviderId = null;
        }
        
        function resetModelForm() {
            $('#custom-model-form')[0].reset();
            $('#model_id').val('');
            $('#model_provider_id').val('');
            $('#save-model-btn').text('Save Model');
        }
        
        // Global functions for button actions
        window.editProvider = function(providerId) {
            // Load provider data and populate form
            $.ajax({
                url: SmartContentAIAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smartcontentai_load_custom_providers',
                    nonce: SmartContentAIAdmin.nonces.loadCustomProviders || ''
                },
                success: function(response) {
                    if (response.success) {
                        var provider = null;
                        $.each(response.data, function(index, p) {
                            if (p.id === providerId) {
                                provider = p;
                                return false;
                            }
                        });
                        
                        if (provider) {
                            populateProviderForm(provider);
                            editingProvider = true;
                            currentProviderId = providerId;
                        }
                    }
                }
            });
        };
        
        function populateProviderForm(provider) {
            $('#provider_id').val(provider.id);
            $('#provider_name').val(provider.name);
            $('#provider_slug').val(provider.slug);
            $('#base_url').val(provider.base_url);
            $('#auth_type').val(provider.auth_type);
            $('#enabled').prop('checked', provider.enabled);
            $('#is_default').prop('checked', provider.is_default);
            
            $('#provider-form-title').text('Edit Provider');
            $('#save-provider-btn').text('Update Provider');
            $('#cancel-edit-btn').show();
        }
        
        window.manageModels = function(providerId, providerName) {
            $('#models-management').show();
            $('#model_provider_id').val(providerId);
            $('#models-section-title').text('Models for ' + providerName);
            
            // Load models for this provider
            loadProviderModels(providerId);
        };
        
        function loadProviderModels(providerId) {
            $.ajax({
                url: SmartContentAIAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smartcontentai_load_custom_providers',
                    nonce: SmartContentAIAdmin.nonces.loadCustomProviders || ''
                },
                success: function(response) {
                    if (response.success) {
                        var provider = null;
                        $.each(response.data, function(index, p) {
                            if (p.id === providerId) {
                                provider = p;
                                return false;
                            }
                        });
                        
                        if (provider) {
                            displayProviderModels(provider.models);
                        }
                    }
                }
            });
        }
        
        function displayProviderModels(models) {
            if (models.length === 0) {
                $('#provider-models-list').html('<p>No models configured for this provider.</p>');
                return;
            }
            
            var html = '<div class="models-grid">';
            
            $.each(models, function(index, model) {
                html += '<div class="model-card">';
                html += '<h5>' + model.name + '</h5>';
                html += '<p><strong>Slug:</strong> ' + model.slug + '</p>';
                html += '<p><strong>Tier:</strong> ' + model.tier + '</p>';
                html += '<p><strong>Max Tokens:</strong> ' + model.max_tokens + '</p>';
                html += '<div class="model-actions">';
                html += '<button type="button" class="button button-small" onclick="editModel(' + model.id + ')">Edit</button>';
                html += '<button type="button" class="button button-small button-link-delete" onclick="deleteModel(' + model.id + ')">Delete</button>';
                html += '</div>';
                html += '</div>';
            });
            
            html += '</div>';
            $('#provider-models-list').html(html);
        }
        
        window.testProvider = function(providerId) {
            $.ajax({
                url: SmartContentAIAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smartcontentai_test_custom_provider',
                    provider_id: providerId,
                    nonce: SmartContentAIAdmin.nonces.testProvider || ''
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('Success: ' + response.data, 'success');
                    } else {
                        showNotification('Error: ' + response.data, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    var errorMsg = 'Connection test failed';
                    if (xhr.responseText) {
                        errorMsg += ': ' + xhr.responseText;
                    }
                    showNotification(errorMsg, 'error');
                }
            });
        };
        
        window.deleteProvider = function(providerId) {
            if (!confirm('Are you sure you want to delete this provider? This will also delete all its models.')) {
                return;
            }
            
            var form = $('<form method="post">' +
                '<input type="hidden" name="action" value="smartcontentai_delete_custom_provider">' +
                '<input type="hidden" name="provider_id" value="' + providerId + '">' +
                '<input type="hidden" name="_wpnonce" value="' + (SmartContentAIAdmin.nonces.customProvider || '') + '">' +
                '</form>');
            
            $('body').append(form);
            form.submit();
        };
        
        // Import models functionality
        $('#import-models-btn').on('click', function() {
            if (!currentProviderId) {
                showNotification('Please save the provider first before importing models.', 'error');
                return;
            }
            
            $(this).prop('disabled', true).text('Importing...');
            
            $.ajax({
                url: SmartContentAIAdmin.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'smartcontentai_import_custom_models',
                    provider_id: currentProviderId,
                    nonce: SmartContentAIAdmin.nonces.importModels || ''
                },
                success: function(response) {
                    $('#import-models-btn').prop('disabled', false).text('Import Models');
                    
                    if (response.success) {
                        showNotification('Success: ' + response.data, 'success');
                        loadProviderModels(currentProviderId);
                    } else {
                        showNotification('Error: ' + response.data, 'error');
                    }
                },
                error: function() {
                    $('#import-models-btn').prop('disabled', false).text('Import Models');
                    showNotification('Import failed. Please check your configuration and try again.', 'error');
                }
            });
        });
        
        // Helper functions for temporary provider storage
        function saveTempProvider(providerData) {
            // For now, return a temporary ID
            return 'temp_' + Date.now();
        }
        
        function deleteTempProvider(tempId) {
            // Clean up would go here
            // Removed console.log for security reasons
        }
    });
})(jQuery);
