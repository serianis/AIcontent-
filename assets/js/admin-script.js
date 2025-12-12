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

    $(function () {
        var $previewBtn = $('#autoblogai-preview-btn');
        var $generateBtn = $('#autoblogai-generate-btn');
        var $topic = $('#autoblogai-topic');
        var $keyword = $('#autoblogai-keyword');
        var $mode = $('#autoblogai-publish-mode');
        var $scheduledAt = $('#autoblogai-scheduled-at');
        var $result = $('#autoblogai-generator-result');
        var $preview = $('#autoblogai-preview');

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
                    $result.html(renderError(AutoblogAIAdmin.i18n.missingTopic));
                    return;
                }

                setLoading($previewBtn, true);
                $result.html('');
                $preview.html('');

                $.post(AutoblogAIAdmin.ajaxUrl, {
                    action: 'autoblogai_preview_post',
                    nonce: AutoblogAIAdmin.nonces.preview,
                    topic: payload.topic,
                    keyword: payload.keyword
                })
                    .done(function (response) {
                        if (!response || !response.success) {
                            $result.html(renderError((response && response.data) ? response.data : AutoblogAIAdmin.i18n.unknownError));
                            return;
                        }
                        $result.html(renderSuccess(AutoblogAIAdmin.i18n.previewReady));
                        $preview.html(response.data.preview_html || '');
                    })
                    .fail(function () {
                        $result.html(renderError(AutoblogAIAdmin.i18n.unknownError));
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
                    $result.html(renderError(AutoblogAIAdmin.i18n.missingTopic));
                    return;
                }

                setLoading($generateBtn, true);
                $result.html(renderSuccess(AutoblogAIAdmin.i18n.generating));

                $.post(AutoblogAIAdmin.ajaxUrl, {
                    action: 'autoblogai_generate',
                    nonce: AutoblogAIAdmin.nonces.generate,
                    topic: payload.topic,
                    keyword: payload.keyword,
                    publish_mode: payload.publish_mode,
                    scheduled_at: payload.scheduled_at
                })
                    .done(function (response) {
                        if (!response || !response.success) {
                            $result.html(renderError((response && response.data) ? response.data : AutoblogAIAdmin.i18n.unknownError));
                            return;
                        }

                        var link = response.data && response.data.link ? response.data.link : '';
                        var msg = AutoblogAIAdmin.i18n.created;
                        if (link) {
                            msg += ' <a href="' + link + '" target="_blank" rel="noopener">' + _.escape(AutoblogAIAdmin.i18n.viewPost) + '</a>';
                        }
                        $result.html('<div class="notice notice-success"><p>' + msg + '</p></div>');
                    })
                    .fail(function () {
                        $result.html(renderError(AutoblogAIAdmin.i18n.unknownError));
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
    });
})(jQuery);
