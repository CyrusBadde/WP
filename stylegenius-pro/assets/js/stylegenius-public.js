/**
 * StyleGenius Pro - Public JavaScript
 *
 * @package StyleGenius_Pro
 */

(function($) {
    'use strict';

    // Global namespace
    window.StyleGenius = window.StyleGenius || {};

    /**
     * Initialize all modules
     */
    StyleGenius.init = function() {
        StyleGenius.Quiz.init();
        StyleGenius.Chat.init();
        StyleGenius.ChatWidget.init();
        StyleGenius.Gamification.init();
        StyleGenius.Toast.init();
    };

    /**
     * Quiz Module
     */
    StyleGenius.Quiz = {
        currentQuestion: 0,
        totalQuestions: 0,
        answers: {},

        init: function() {
            var $quiz = $('#sg-quiz');
            if (!$quiz.length) return;

            this.$quiz = $quiz;
            this.$intro = $('#sg-quiz-intro');
            this.$form = $('#sg-quiz-form');
            this.$processing = $('#sg-quiz-processing');
            this.$result = $('#sg-quiz-result');
            this.totalQuestions = $quiz.find('.sg-quiz-question').length;

            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Start quiz
            $('#sg-quiz-start').on('click', function() {
                self.$intro.fadeOut(300, function() {
                    self.$form.fadeIn(300);
                });
            });

            // Next question
            $('#sg-quiz-next').on('click', function() {
                if (self.validateCurrentQuestion()) {
                    self.nextQuestion();
                }
            });

            // Previous question
            $('#sg-quiz-prev').on('click', function() {
                self.prevQuestion();
            });

            // Submit quiz
            this.$form.on('submit', function(e) {
                e.preventDefault();
                if (self.validateCurrentQuestion()) {
                    self.submitQuiz();
                }
            });

            // Track answer changes
            this.$form.on('change', 'input', function() {
                self.saveAnswer($(this));
            });
        },

        validateCurrentQuestion: function() {
            var $question = this.$form.find('.sg-quiz-question').eq(this.currentQuestion);
            var $inputs = $question.find('input[type="radio"], input[type="checkbox"]');

            if ($inputs.length && !$inputs.filter(':checked').length) {
                StyleGenius.Toast.show(styleGeniusData.strings.error, 'error');
                return false;
            }
            return true;
        },

        saveAnswer: function($input) {
            var name = $input.attr('name');
            var value = $input.val();

            if ($input.attr('type') === 'checkbox') {
                if (!this.answers[name]) this.answers[name] = [];
                if ($input.is(':checked')) {
                    this.answers[name].push(value);
                } else {
                    this.answers[name] = this.answers[name].filter(function(v) {
                        return v !== value;
                    });
                }
            } else {
                this.answers[name] = value;
            }
        },

        nextQuestion: function() {
            if (this.currentQuestion < this.totalQuestions - 1) {
                this.showQuestion(this.currentQuestion + 1);
            }
        },

        prevQuestion: function() {
            if (this.currentQuestion > 0) {
                this.showQuestion(this.currentQuestion - 1);
            }
        },

        showQuestion: function(index) {
            var self = this;
            var $questions = this.$form.find('.sg-quiz-question');

            $questions.eq(this.currentQuestion).fadeOut(200, function() {
                $questions.eq(index).fadeIn(200);
                self.currentQuestion = index;
                self.updateNavigation();
                self.updateProgress();
            });
        },

        updateNavigation: function() {
            var $prev = $('#sg-quiz-prev');
            var $next = $('#sg-quiz-next');
            var $submit = $('#sg-quiz-submit');

            $prev.toggle(this.currentQuestion > 0);

            if (this.currentQuestion === this.totalQuestions - 1) {
                $next.hide();
                $submit.show();
            } else {
                $next.show();
                $submit.hide();
            }
        },

        updateProgress: function() {
            var percent = ((this.currentQuestion + 1) / this.totalQuestions) * 100;
            $('#sg-quiz-progress-fill').css('width', percent + '%');
            $('#sg-quiz-current').text(this.currentQuestion + 1);
        },

        submitQuiz: function() {
            var self = this;

            this.$form.fadeOut(300, function() {
                self.$processing.fadeIn(300);
                self.showProcessingSteps();
            });

            // Submit via AJAX
            $.ajax({
                url: styleGeniusData.restUrl + 'quiz/submit',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': styleGeniusData.restNonce
                },
                data: {
                    answers: this.answers
                },
                success: function(response) {
                    setTimeout(function() {
                        self.showResult(response);
                    }, 2000);
                },
                error: function() {
                    StyleGenius.Toast.show(styleGeniusData.strings.error, 'error');
                    self.$processing.fadeOut(300, function() {
                        self.$form.fadeIn(300);
                    });
                }
            });
        },

        showProcessingSteps: function() {
            var steps = [
                'Analysiere deine Antworten...',
                'Ermittle deinen Stil-Typ...',
                'Erstelle dein Profil...'
            ];
            var $step = $('#sg-processing-step');
            var index = 0;

            var interval = setInterval(function() {
                $step.fadeOut(200, function() {
                    if (index < steps.length) {
                        $step.text(steps[index]).fadeIn(200);
                        index++;
                    } else {
                        clearInterval(interval);
                    }
                });
            }, 800);
        },

        showResult: function(data) {
            var self = this;

            this.$processing.fadeOut(300, function() {
                self.$result.fadeIn(300);
                self.renderResultCard(data);
                self.triggerCelebration();

                // Award points notification
                if (data.points_earned) {
                    StyleGenius.Gamification.showPointsPopup(data.points_earned, 'Style-Quiz abgeschlossen');
                }
            });
        },

        renderResultCard: function(data) {
            var html = '<div class="sg-style-type-icon">' + (data.icon || '✨') + '</div>' +
                '<h3 class="sg-style-type-name">' + data.style_name + '</h3>' +
                '<p class="sg-style-type-description">' + data.description + '</p>';

            $('#sg-result-card').html(html);
        },

        triggerCelebration: function() {
            if (typeof confetti !== 'undefined') {
                confetti({
                    particleCount: 100,
                    spread: 70,
                    origin: { y: 0.6 }
                });
            }
        }
    };

    /**
     * Chat Module
     */
    StyleGenius.Chat = {
        isProcessing: false,
        imageData: null,

        init: function() {
            var $chat = $('#sg-chat');
            if (!$chat.length) return;

            this.$chat = $chat;
            this.$messages = $('#sg-chat-messages');
            this.$input = $('#sg-chat-input');
            this.$form = $('#sg-chat-form');
            this.$typing = $('#sg-chat-typing');
            this.$quickActions = $('#sg-chat-quick-actions');

            this.bindEvents();
            this.scrollToBottom();
        },

        bindEvents: function() {
            var self = this;

            // Form submit
            this.$form.on('submit', function(e) {
                e.preventDefault();
                self.sendMessage();
            });

            // Input changes
            this.$input.on('input', function() {
                self.autoResize(this);
                self.updateSendButton();
            });

            // Enter to send (without shift)
            this.$input.on('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    self.sendMessage();
                }
            });

            // Quick action buttons
            $('.sg-chat-quick-btn').on('click', function() {
                var message = $(this).data('message');
                self.$input.val(message);
                self.sendMessage();
            });

            // Image upload
            $('#sg-chat-upload-btn').on('click', function() {
                $('#sg-chat-file-input').click();
            });

            $('#sg-chat-file-input').on('change', function() {
                self.handleImageUpload(this.files[0]);
            });

            $('#sg-chat-image-remove').on('click', function() {
                self.removeImage();
            });

            // Clear history
            $('#sg-chat-clear').on('click', function() {
                if (confirm('Verlauf wirklich löschen?')) {
                    self.clearHistory();
                }
            });
        },

        autoResize: function(textarea) {
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        },

        updateSendButton: function() {
            var hasContent = this.$input.val().trim().length > 0 || this.imageData;
            $('#sg-chat-send').prop('disabled', !hasContent || this.isProcessing);
        },

        sendMessage: function() {
            var message = this.$input.val().trim();

            if ((!message && !this.imageData) || this.isProcessing) return;

            this.isProcessing = true;
            this.$quickActions.hide();

            // Add user message
            this.addMessage(message, 'user', this.imageData);

            // Clear input
            this.$input.val('');
            this.autoResize(this.$input[0]);
            this.updateSendButton();

            // Show typing indicator
            this.$typing.show();
            this.scrollToBottom();

            // Send to API
            var self = this;
            $.ajax({
                url: styleGeniusData.restUrl + 'chat/message',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': styleGeniusData.restNonce
                },
                data: {
                    message: message,
                    image: this.imageData
                },
                success: function(response) {
                    self.$typing.hide();
                    self.addMessage(response.message, 'assistant');

                    // Update remaining count
                    if (response.remaining !== undefined) {
                        $('#sg-chat-remaining').text(response.remaining);
                    }

                    self.isProcessing = false;
                    self.updateSendButton();
                },
                error: function(xhr) {
                    self.$typing.hide();
                    var error = xhr.responseJSON?.message || styleGeniusData.strings.chatError;
                    StyleGenius.Toast.show(error, 'error');
                    self.isProcessing = false;
                    self.updateSendButton();
                }
            });

            // Clear image
            this.removeImage();
        },

        addMessage: function(content, role, image) {
            var html = '<div class="sg-chat-message sg-chat-message--' + role + '">';

            if (role === 'assistant') {
                html += '<div class="sg-chat-message-avatar">' +
                    '<div class="sg-avatar sg-avatar--ai">' +
                    '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                    '<path d="M12 2l2 7h7l-5.5 4 2 7L12 16l-5.5 4 2-7L3 9h7z"></path>' +
                    '</svg></div></div>';
            }

            html += '<div class="sg-chat-message-content">';

            if (image) {
                html += '<div class="sg-chat-message-image"><img src="' + image + '" alt=""></div>';
            }

            html += '<p>' + this.formatMessage(content) + '</p>';
            html += '<span class="sg-chat-message-time">Gerade eben</span>';
            html += '</div></div>';

            this.$messages.append(html);
            this.scrollToBottom();
        },

        formatMessage: function(text) {
            // Convert line breaks
            text = text.replace(/\n/g, '<br>');

            // Bold text
            text = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // Italic text
            text = text.replace(/\*(.*?)\*/g, '<em>$1</em>');

            return text;
        },

        handleImageUpload: function(file) {
            if (!file || !file.type.startsWith('image/')) return;

            var self = this;
            var reader = new FileReader();

            reader.onload = function(e) {
                self.imageData = e.target.result;
                $('#sg-chat-preview-img').attr('src', self.imageData);
                $('#sg-chat-image-preview').show();
                self.updateSendButton();
            };

            reader.readAsDataURL(file);
        },

        removeImage: function() {
            this.imageData = null;
            $('#sg-chat-image-preview').hide();
            $('#sg-chat-file-input').val('');
            this.updateSendButton();
        },

        clearHistory: function() {
            var self = this;

            $.ajax({
                url: styleGeniusData.restUrl + 'chat/clear',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': styleGeniusData.restNonce
                },
                success: function() {
                    self.$messages.find('.sg-chat-message:not(.sg-chat-message--welcome)').remove();
                    self.$quickActions.show();
                    StyleGenius.Toast.show('Verlauf gelöscht', 'success');
                }
            });
        },

        scrollToBottom: function() {
            this.$messages.scrollTop(this.$messages[0].scrollHeight);
        }
    };

    /**
     * Chat Widget Module
     */
    StyleGenius.ChatWidget = {
        isOpen: false,

        init: function() {
            var $widget = $('#sg-chat-widget');
            if (!$widget.length) return;

            this.$widget = $widget;
            this.$toggle = $widget.find('.sg-chat-widget-toggle');
            this.$messages = $('#sg-chat-widget-messages');
            this.$input = $('#sg-chat-widget-input');

            this.bindEvents();

            // Show widget after delay
            setTimeout(function() {
                $widget.fadeIn();
            }, 2000);
        },

        bindEvents: function() {
            var self = this;

            this.$toggle.on('click', function() {
                self.toggle();
            });

            // Send message
            $('#sg-chat-widget-send').on('click', function() {
                self.sendMessage();
            });

            this.$input.on('keypress', function(e) {
                if (e.key === 'Enter') {
                    self.sendMessage();
                }
            });
        },

        toggle: function() {
            this.isOpen = !this.isOpen;
            this.$widget.toggleClass('is-open', this.isOpen);
            this.$widget.find('.sg-chat-widget-icon--open').toggle(!this.isOpen);
            this.$widget.find('.sg-chat-widget-icon--close').toggle(this.isOpen);

            if (this.isOpen) {
                this.$input.focus();
            }
        },

        sendMessage: function() {
            var message = this.$input.val().trim();
            if (!message) return;

            // Add user message
            this.addMessage(message, 'user');
            this.$input.val('');

            // Send to API
            var self = this;
            $.ajax({
                url: styleGeniusData.restUrl + 'chat/message',
                method: 'POST',
                headers: {
                    'X-WP-Nonce': styleGeniusData.restNonce
                },
                data: { message: message },
                success: function(response) {
                    self.addMessage(response.message, 'assistant');
                },
                error: function() {
                    self.addMessage('Entschuldigung, ein Fehler ist aufgetreten.', 'assistant');
                }
            });
        },

        addMessage: function(content, role) {
            var html = '<div class="sg-chat-message sg-chat-message--' + role + '">' +
                '<div class="sg-chat-message-content">' + content + '</div></div>';

            this.$messages.append(html);
            this.$messages.scrollTop(this.$messages[0].scrollHeight);
        }
    };

    /**
     * Gamification Module
     */
    StyleGenius.Gamification = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Listen for gamification events
            $(document).on('stylegenius:points', function(e, data) {
                StyleGenius.Gamification.showPointsPopup(data.points, data.reason);
            });

            $(document).on('stylegenius:levelup', function(e, data) {
                StyleGenius.Gamification.showLevelUpPopup(data.level, data.title);
            });

            $(document).on('stylegenius:badge', function(e, data) {
                StyleGenius.Gamification.showBadgePopup(data.name, data.description, data.icon);
            });
        },

        showPointsPopup: function(points, reason) {
            StyleGenius.Toast.show('+' + points + ' Punkte', 'points', reason);
        },

        showLevelUpPopup: function(level, title) {
            var html = '<div class="sg-gamification-popup sg-gamification-popup--level" data-gamification-popup>' +
                '<div class="sg-gamification-popup-overlay"></div>' +
                '<div class="sg-gamification-popup-content">' +
                '<div class="sg-gamification-popup-icon sg-gamification-popup-icon--level">' +
                '<span class="sg-level-badge">' + level + '</span></div>' +
                '<h3>Level Up!</h3>' +
                '<p>Du hast Level ' + level + ' erreicht!</p>' +
                '<p class="sg-gamification-popup-title">' + title + '</p>' +
                '<button type="button" class="sg-button sg-button--primary sg-gamification-popup-close">Super!</button>' +
                '</div></div>';

            var $popup = $(html).appendTo('body');

            $popup.find('.sg-gamification-popup-close, .sg-gamification-popup-overlay').on('click', function() {
                $popup.fadeOut(300, function() {
                    $popup.remove();
                });
            });

            // Confetti
            if (typeof confetti !== 'undefined') {
                confetti({ particleCount: 150, spread: 100 });
            }
        },

        showBadgePopup: function(name, description, icon) {
            var iconHtml = icon ?
                '<img src="' + icon + '" alt="">' :
                '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<circle cx="12" cy="8" r="6"></circle><path d="M15.477 12.89 17 22l-5-3-5 3 1.523-9.11"></path></svg>';

            var html = '<div class="sg-gamification-popup sg-gamification-popup--badge" data-gamification-popup>' +
                '<div class="sg-gamification-popup-overlay"></div>' +
                '<div class="sg-gamification-popup-content">' +
                '<div class="sg-gamification-popup-icon sg-gamification-popup-icon--badge">' + iconHtml + '</div>' +
                '<h3>Neues Abzeichen!</h3>' +
                '<p class="sg-badge-name">' + name + '</p>' +
                '<p>' + description + '</p>' +
                '<button type="button" class="sg-button sg-button--primary sg-gamification-popup-close">Super!</button>' +
                '</div></div>';

            var $popup = $(html).appendTo('body');

            $popup.find('.sg-gamification-popup-close, .sg-gamification-popup-overlay').on('click', function() {
                $popup.fadeOut(300, function() {
                    $popup.remove();
                });
            });
        }
    };

    /**
     * Toast Notifications
     */
    StyleGenius.Toast = {
        $container: null,

        init: function() {
            this.$container = $('<div class="sg-toast-container"></div>').appendTo('body');
        },

        show: function(message, type, subtitle) {
            type = type || 'info';

            var icons = {
                success: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>',
                error: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
                warning: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>',
                info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>',
                points: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon></svg>'
            };

            var html = '<div class="sg-toast sg-toast--' + type + '">' +
                '<div class="sg-toast-icon">' + icons[type] + '</div>' +
                '<div class="sg-toast-content">' +
                '<p class="sg-toast-message">' + message + '</p>' +
                (subtitle ? '<p class="sg-toast-subtitle">' + subtitle + '</p>' : '') +
                '</div>' +
                '<button type="button" class="sg-toast-close">' +
                '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">' +
                '<line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>' +
                '</button></div>';

            var $toast = $(html).appendTo(this.$container);

            $toast.find('.sg-toast-close').on('click', function() {
                $toast.fadeOut(200, function() {
                    $toast.remove();
                });
            });

            // Auto remove after 5 seconds
            setTimeout(function() {
                $toast.fadeOut(200, function() {
                    $toast.remove();
                });
            }, 5000);
        }
    };

    /**
     * Utility Functions
     */
    StyleGenius.Utils = {
        formatNumber: function(num) {
            return new Intl.NumberFormat('de-DE').format(num);
        },

        debounce: function(func, wait) {
            var timeout;
            return function() {
                var context = this, args = arguments;
                clearTimeout(timeout);
                timeout = setTimeout(function() {
                    func.apply(context, args);
                }, wait);
            };
        },

        copyToClipboard: function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            } else {
                var $temp = $('<textarea>').val(text).appendTo('body').select();
                document.execCommand('copy');
                $temp.remove();
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        StyleGenius.init();
    });

})(jQuery);
