document.addEventListener('DOMContentLoaded', function() {
    // 削除: 封筒演出のコード
    const invitationContent = document.querySelector('.invitation-content');
    const choiceScreen = document.querySelector('.choice-screen');
    
    // モバイルデバイス検出を強化
    const isMobile = {
        Android: function() {
            return navigator.userAgent.match(/Android/i);
        },
        iOS: function() {
            return navigator.userAgent.match(/iPhone|iPad|iPod/i);
        },
        Windows: function() {
            return navigator.userAgent.match(/IEMobile/i) || navigator.userAgent.match(/WPDesktop/i);
        },
        any: function() {
            return (isMobile.Android() || isMobile.iOS() || isMobile.Windows());
        }
    };
    
    // モバイル端末かどうかを検出
    const isMobileDevice = isMobile.any();
    
    // エフェクト数をモバイルの場合は減らす
    if (isMobileDevice) {
        console.log('モバイルデバイスを検出しました');
        
        // 画像の遅延読み込みを最適化
        const lazyImages = document.querySelectorAll('img[loading="lazy"]');
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        observer.unobserve(img);
                    }
                });
            }, { rootMargin: '50px' });
            
            lazyImages.forEach(img => imageObserver.observe(img));
        }
        
        // モバイルでのタッチイベント最適化
        document.addEventListener('touchstart', function() {}, {passive: true});
        
        // アニメーション効果を軽減
        document.documentElement.classList.add('mobile-device');
        
        // iOS Safariでのスクロール問題対策
        document.body.addEventListener('touchmove', function(e) {
            if (document.body.classList.contains('no-scroll')) {
                e.preventDefault();
            }
        }, { passive: false });
        
        // モバイルでは半分のエフェクトのみ表示
        const allEffects = document.querySelectorAll('.floating-heart, .floating-sparkle');
        for (let i = 0; i < allEffects.length; i++) {
            if (i % 2 !== 0) {
                allEffects[i].style.display = 'none';
            }
        }
    }
    
    // クリック/タップエフェクト - 全ページ共通
    // モバイルではエフェクトを軽量化
    document.addEventListener('click', function(e) {
        // モバイル端末では5回に1回だけエフェクトを表示（負荷軽減）
        if (isMobileDevice && Math.random() > 0.2) return;
        
        const clickEffect = document.createElement('div');
        clickEffect.classList.add('click-effect');
        
        // クリック位置を設定
        clickEffect.style.left = e.pageX + 'px';
        clickEffect.style.top = e.pageY + 'px';
        
        // サイズをランダムに（モバイルではやや小さめに）
        const size = isMobileDevice ? (30 + Math.random() * 60) : (50 + Math.random() * 100);
        clickEffect.style.width = size + 'px';
        clickEffect.style.height = size + 'px';
        clickEffect.style.marginLeft = -size / 2 + 'px';
        clickEffect.style.marginTop = -size / 2 + 'px';
        
        // 色をランダムに（淡いピンクやゴールド色）
        const colors = [
            'rgba(255,192,203,0.7)',
            'rgba(255,215,0,0.4)',
            'rgba(255,182,193,0.6)',
            'rgba(255,228,196,0.5)',
            'rgba(255,182,193,0.5)'
        ];
        const randomColor = colors[Math.floor(Math.random() * colors.length)];
        clickEffect.style.background = `radial-gradient(circle, ${randomColor} 0%, rgba(255,255,255,0) 70%)`;
        
        // ページに追加
        document.body.appendChild(clickEffect);
        
        // アニメーション終了後に要素を削除
        setTimeout(() => {
            if (document.body.contains(clickEffect)) {
                document.body.removeChild(clickEffect);
            }
        }, isMobileDevice ? 700 : 1000); // モバイルでは短縮
    });
    
    // 削除: 封筒関連のコード
    
    // 招待状カードをクリックしたときの処理
    const invitationCard = document.querySelector('.choice-invitation-card');
    if (invitationCard) {
        invitationCard.addEventListener('click', function() {
            console.log('招待状カードがクリックされました');
            
            // アニメーション用に事前準備
            // 招待状コンテンツを準備（表示するが透明に）
            invitationContent.style.opacity = '0';
            invitationContent.style.display = 'flex';
            invitationContent.style.transform = 'translateY(10px)';
            
            // 選択画面をフェードアウト
            choiceScreen.style.transition = 'opacity 0.3s ease';
            choiceScreen.style.opacity = '0';
            
            // フェードアウト完了と同時に切り替え
            setTimeout(() => {
                // 選択画面を完全に非表示
                choiceScreen.classList.add('hide');
                
                // 招待状を表示
                invitationContent.classList.remove('hide');
                
                // 強制リフロー
                void invitationContent.offsetWidth;
                
                // フェードイン
                invitationContent.style.transition = 'opacity 0.4s ease, transform 0.4s ease';
                invitationContent.style.opacity = '1';
                invitationContent.style.transform = 'translateY(0)';
                
                // コンテンツを順番にフェードイン
                animateInvitationContent();
            }, 300);
        });
    }
    
    // 付箋カードをクリックしたときの処理
    const fusenCards = document.querySelectorAll('.choice-fusen-card');
    fusenCards.forEach(card => {
        card.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('付箋カードがクリックされました');
            const fusenUrl = this.getAttribute('data-url') || this.getAttribute('href');
            if (fusenUrl && fusenUrl !== '#') {
                // フェードアウトしてから遷移
                document.body.style.opacity = '0.7';
                document.body.style.transition = 'opacity 0.3s ease';
                setTimeout(() => {
                    window.location.href = fusenUrl;
                }, 300);
            }
        });
    });
    
    // 招待状コンテンツのアニメーション関数
    function animateInvitationContent() {
        const animatedElements = document.querySelectorAll('.main-header, .video-container, .story-section, .timeline-section, .wedding-info, .countdown-section, .gallery, .rsvp-section, .message-section');
        
        // メインヘッダーをすぐに表示
        if (animatedElements.length > 0) {
            const mainHeader = document.querySelector('.main-header');
            if (mainHeader) {
                mainHeader.style.willChange = 'opacity, transform';
                mainHeader.classList.add('fade-in');
            }
        }

        // パフォーマンス最適化: requestAnimationFrameを使用
        let index = 1; // メインヘッダーをスキップ
        
        function animateNext() {
            if (index < animatedElements.length) {
                const element = animatedElements[index];
                
                // will-changeプロパティを追加して、ブラウザが最適化できるようにする
                element.style.willChange = 'opacity, transform';
                
                // 非同期的にアニメーションクラスを追加
                requestAnimationFrame(() => {
                    element.classList.add('fade-in');
                    
                    // アニメーション終了後にwill-changeを削除
                    element.addEventListener('animationend', function animEndHandler() {
                        element.style.willChange = 'auto';
                        element.removeEventListener('animationend', animEndHandler);
                    }, { once: true });
                    
                    index++;
                    
                    // 次の要素を70ms後にアニメーション（間隔を短くした）
                    setTimeout(animateNext, 70);
                });
            }
        }
        
        // アニメーション開始（少し早く開始）
        setTimeout(animateNext, 50);
    }
    
    // カウントダウンタイマー
    function updateCountdown() {
        const countdownElement = document.querySelector('.countdown-timer');
        const weddingDateAttr = countdownElement ? countdownElement.getAttribute('data-wedding-date') : '2025-04-30';
        const weddingDate = new Date(`${weddingDateAttr}T13:00:00+09:00`).getTime();
        const now = new Date().getTime();
        const timeLeft = weddingDate - now;
        
        // 結婚式の日付が過ぎている場合
        if (timeLeft < 0) {
            document.getElementById('countdown-days').innerText = '00';
            document.getElementById('countdown-hours').innerText = '00';
            document.getElementById('countdown-minutes').innerText = '00';
            document.getElementById('countdown-seconds').innerText = '00';
            return;
        }
        
        // 日、時、分、秒を計算
        const days = Math.floor(timeLeft / (1000 * 60 * 60 * 24));
        const hours = Math.floor((timeLeft % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((timeLeft % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
        
        // HTMLを更新
        document.getElementById('countdown-days').innerText = days.toString().padStart(2, '0');
        document.getElementById('countdown-hours').innerText = hours.toString().padStart(2, '0');
        document.getElementById('countdown-minutes').innerText = minutes.toString().padStart(2, '0');
        document.getElementById('countdown-seconds').innerText = seconds.toString().padStart(2, '0');
    }
    
    // カウントダウンを1秒ごとに更新
    updateCountdown();
    setInterval(updateCountdown, 1000);
    
    // 出席ラジオボタンの切り替えによる追加フィールドの表示/非表示
    const attendYesRadio = document.getElementById('attend-yes');
    const attendNoRadio = document.getElementById('attend-no');
    const attendanceDetails = document.querySelector('.attendance-details');
    
    if (attendYesRadio && attendNoRadio && attendanceDetails) {
        attendYesRadio.addEventListener('change', function() {
            if (this.checked) {
                attendanceDetails.style.display = 'block';
                
                // スムーズなアニメーション
                attendanceDetails.style.opacity = '0';
                attendanceDetails.style.maxHeight = '0';
                
                setTimeout(() => {
                    attendanceDetails.style.transition = 'opacity 0.5s, max-height 0.5s';
                    attendanceDetails.style.opacity = '1';
                    attendanceDetails.style.maxHeight = '200px';
                }, 10);
            }
        });
        
        attendNoRadio.addEventListener('change', function() {
            if (this.checked) {
                attendanceDetails.style.transition = 'opacity 0.5s, max-height 0.5s';
                attendanceDetails.style.opacity = '0';
                attendanceDetails.style.maxHeight = '0';
                
                setTimeout(() => {
                    attendanceDetails.style.display = 'none';
                }, 500);
            }
        });
        
        // 初期状態をチェック
        if (attendYesRadio.checked) {
            attendanceDetails.style.display = 'block';
        }
    }
    
    // 同伴者人数選択による同伴者情報フォームの動的生成
    const guestsSelect = document.getElementById('guests');
    const companionsContainer = document.getElementById('companions-container');
    const companionsFields = document.getElementById('companions-fields');
    
    if (guestsSelect && companionsContainer && companionsFields) {
        guestsSelect.addEventListener('change', function() {
            const count = parseInt(this.value, 10);
            
            // コンテナの表示/非表示を切り替え
            if (count > 0) {
                companionsContainer.style.display = 'block';
                // 同伴者フィールドを生成
                generateCompanionFields(count);
            } else {
                companionsContainer.style.display = 'none';
                // 同伴者フィールドをクリア
                companionsFields.innerHTML = '';
            }
        });
        
        // 同伴者フィールドを生成する関数
        function generateCompanionFields(count) {
            companionsFields.innerHTML = ''; // 既存のフィールドをクリア
            
            for (let i = 1; i <= count; i++) {
                const companionDiv = document.createElement('div');
                companionDiv.className = 'companion-entry';
                companionDiv.innerHTML = `
                    <div class="form-group companion-form-group">
                        <label for="companion-name-${i}">同伴者 ${i} - お名前<span class="required">*</span></label>
                        <input type="text" id="companion-name-${i}" name="companion_name[]" required class="companion-field">
                    </div>
                    <div class="form-group companion-form-group">
                        <label for="companion-age-${i}">年齢区分</label>
                        <select id="companion-age-${i}" name="companion_age[]" class="companion-field">
                            <option value="adult">大人</option>
                            <option value="child">子供（小学生以下）</option>
                            <option value="infant">幼児（3歳以下）</option>
                        </select>
                    </div>
                    <div class="form-group companion-form-group">
                        <label for="companion-dietary-${i}">アレルギー・食事制限等</label>
                        <textarea id="companion-dietary-${i}" name="companion_dietary[]" rows="2" class="companion-field"></textarea>
                    </div>
                `;
                companionsFields.appendChild(companionDiv);
            }
            
            // スタイルを追加
            const style = document.createElement('style');
            style.textContent = `
                .companion-entry {
                    margin-bottom: 20px;
                    padding: 15px;
                    border: 1px solid var(--accent-light);
                    border-radius: 8px;
                    background-color: rgba(255, 255, 255, 0.8);
                }
                
                .companion-form-group {
                    margin-bottom: 10px;
                }
                
                .companions-note {
                    font-size: 0.9rem;
                    color: #555;
                    margin-bottom: 15px;
                }
            `;
            if (!document.getElementById('companion-styles')) {
                style.id = 'companion-styles';
                document.head.appendChild(style);
            }
        }
        
        // 初期状態をチェック（ページロード時に値があれば）
        if (parseInt(guestsSelect.value, 10) > 0) {
            companionsContainer.style.display = 'block';
            generateCompanionFields(parseInt(guestsSelect.value, 10));
        }
    }
    
    // フォーム送信処理
    const rsvpForm = document.getElementById('rsvp-form');
    if (rsvpForm) {
        // フォームのリセット（エラー状態をクリア）
        function resetFormErrors() {
            const errorInputs = rsvpForm.querySelectorAll('.error-input');
            const errorMessages = rsvpForm.querySelectorAll('.error-message-inline');
            
            errorInputs.forEach(input => {
                input.classList.remove('error-input');
            });
            
            errorMessages.forEach(message => {
                message.style.display = 'none';
            });
        }
        
        // フォーム入力欄の検証
        function validateInput(input, errorElement, errorMessage) {
            if (!input.value.trim()) {
                input.classList.add('error-input');
                if (errorElement) {
                    errorElement.textContent = errorMessage;
                    errorElement.style.display = 'block';
                }
                return false;
            }
            return true;
        }
        
        // メールアドレスの検証
        function validateEmail(email, errorElement) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email.value.trim())) {
                email.classList.add('error-input');
                if (errorElement) {
                    errorElement.textContent = '有効なメールアドレスを入力してください';
                    errorElement.style.display = 'block';
                }
                return false;
            }
            return true;
        }
        
        // 送信中フラグ
        let isSubmitting = false;
        
        rsvpForm.addEventListener('submit', function(e) {
            // 既に送信中の場合は処理を中止
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }
            
            // エラー状態をリセット
            resetFormErrors();
            
            // バリデーションチェック
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const nameError = document.getElementById('name-error');
            const emailError = document.getElementById('email-error');
            
            // 基本的なフォームバリデーション
            let isValid = true;
            
            // 名前の検証
            if (!validateInput(nameInput, nameError, 'お名前を入力してください')) {
                isValid = false;
            }
            
            // メールアドレスの検証
            if (!validateEmail(emailInput, emailError)) {
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault(); // 送信を停止
                // エラーがある要素までスクロール
                const firstError = rsvpForm.querySelector('.error-input');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                return false;
            }

            // 送信ボタンをロード中に変更
            const submitButton = this.querySelector('.submit-button');
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 送信中...';
            submitButton.disabled = true;
            
            // 送信中フラグをセット
            isSubmitting = true;
            
            // フォームのデータを送信
            // 通常のフォーム送信が行われる
        });
        
        // 入力欄の変更時にリアルタイムバリデーション
        const nameInput = document.getElementById('name');
        const emailInput = document.getElementById('email');
        
        if (nameInput) {
            nameInput.addEventListener('input', function() {
                if (this.classList.contains('error-input')) {
                    const nameError = document.getElementById('name-error');
                    validateInput(this, nameError, 'お名前を入力してください');
                }
            });
        }
        
        if (emailInput) {
            emailInput.addEventListener('input', function() {
                if (this.classList.contains('error-input')) {
                    const emailError = document.getElementById('email-error');
                    validateEmail(this, emailError);
                }
            });
        }
    }
    
    // スムーズスクロール
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            
            let targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            let targetElement = document.querySelector(targetId);
            if (!targetElement) return;
            
            // モバイルナビゲーションを閉じる
            if (document.body.classList.contains('mobile-nav-active')) {
                document.body.classList.remove('mobile-nav-active');
                document.body.classList.remove('no-scroll');
                const mobileNav = document.getElementById('mobile-nav-toggle');
                if (mobileNav) mobileNav.classList.remove('active');
            }
            
            // スムーススクロール実行
            const yOffset = -60; // ヘッダー高さ分オフセット
            const y = targetElement.getBoundingClientRect().top + window.pageYOffset + yOffset;
            
            window.scrollTo({
                top: y,
                behavior: 'smooth'
            });
        });
    });
    
    // 写真のホバーエフェクト強化
    const photoItems = document.querySelectorAll('.photo-item');
    photoItems.forEach(item => {
        item.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.02)';
            this.style.boxShadow = '0 15px 35px rgba(0, 0, 0, 0.2)';
        });
        
        item.addEventListener('mouseleave', function() {
            this.style.transform = '';
            this.style.boxShadow = '';
        });
    });
    
    // 動画関連の処理
    const video = document.getElementById('wedding-video');
    const videoOverlay = document.querySelector('.video-overlay');
    const playButton = document.querySelector('.play-button');
    
    if (video && videoOverlay && playButton) {
        // 再生ボタンクリック
        playButton.addEventListener('click', function() {
            video.muted = false; // ミュートを解除（モバイルでも音が出る）
            video.play();
            videoOverlay.style.opacity = '0';
            
            setTimeout(() => {
                videoOverlay.style.display = 'none';
            }, 500);
        });
        
        // 動画が終了したら再度オーバーレイを表示
        video.addEventListener('ended', function() {
            videoOverlay.style.display = 'flex';
            videoOverlay.style.opacity = '1';
            // 再生位置をリセット
            video.currentTime = 0;
        });
        
        // 動画をクリックした場合の一時停止/再生
        video.addEventListener('click', function() {
            if (video.paused) {
                video.play();
            } else {
                video.pause();
            }
        });
    }
    
    // アニメーションエフェクト
    const animateOnScroll = function() {
        const elements = document.querySelectorAll('.info-card, .story-section, .timeline-section, .gallery, .rsvp-section, .message-card, .countdown-container');
        
        elements.forEach(element => {
            const elementPosition = element.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (elementPosition < windowHeight - 100) {
                element.classList.add('fade-in');
            }
        });
    };
    
    // スクロール時のアニメーション実行
    window.addEventListener('scroll', animateOnScroll);
    // 初期ロード時にも実行
    setTimeout(animateOnScroll, 500);
    
    // 葉っぱが舞うエフェクト - すでにCSSで実装されているため削除
    
    // テキストリンクのホバーエフェクト
    const textLinks = document.querySelectorAll('a:not(.rsvp-button)');
    textLinks.forEach(link => {
        link.addEventListener('mouseenter', function() {
            this.style.color = 'var(--accent-color)';
            this.style.transition = 'color 0.3s';
        });
        
        link.addEventListener('mouseleave', function() {
            this.style.color = '';
        });
    });
    
    // FAQの開閉処理
    // FAQ質問クリック時の動作
    const faqQuestions = document.querySelectorAll('.faq-question');
    
    console.log('FAQの質問要素数:', faqQuestions.length); // デバッグ情報
    
    faqQuestions.forEach(question => {
        question.addEventListener('click', function() {
            console.log('FAQがクリックされました'); // デバッグ情報
            // 親要素（faq-item）にactiveクラスをトグル
            this.parentElement.classList.toggle('active');
            console.log('activeクラスをトグルしました:', this.parentElement.classList.contains('active')); // デバッグ情報
        });
    });
    
    // 招待状コンテンツを表示状態に設定
    if (invitationContent && !invitationContent.classList.contains('hide')) {
        setTimeout(() => {
            invitationContent.classList.add('visible');
        }, 50);
    }
    
    // レイジーロード用の画像を特定
    const lazyImages = document.querySelectorAll('img.lazy-load');
    
    // 画像が表示領域に入ったときに読み込む
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const lazyImage = entry.target;
                    if (lazyImage.dataset.src) {
                        lazyImage.src = lazyImage.dataset.src;
                        lazyImage.addEventListener('load', () => {
                            lazyImage.classList.add('loaded');
                        });
                        imageObserver.unobserve(lazyImage);
                    }
                }
            });
        });
        
        lazyImages.forEach(image => {
            imageObserver.observe(image);
        });
    } else {
        // IntersectionObserverがサポートされていない場合のフォールバック
        let lazyLoadThrottleTimeout;
        
        function lazyLoad() {
            if (lazyLoadThrottleTimeout) {
                clearTimeout(lazyLoadThrottleTimeout);
            }
            
            lazyLoadThrottleTimeout = setTimeout(() => {
                const scrollTop = window.pageYOffset;
                lazyImages.forEach(img => {
                    if (img.offsetTop < (window.innerHeight + scrollTop)) {
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.addEventListener('load', () => {
                                img.classList.add('loaded');
                            });
                        }
                    }
                });
                
                if (lazyImages.length === 0) {
                    document.removeEventListener('scroll', lazyLoad);
                    window.removeEventListener('resize', lazyLoad);
                    window.removeEventListener('orientationChange', lazyLoad);
                }
            }, 20);
        }
        
        document.addEventListener('scroll', lazyLoad);
        window.addEventListener('resize', lazyLoad);
        window.addEventListener('orientationChange', lazyLoad);
        
        // 初回実行
        lazyLoad();
    }
    
    // 遅延読み込みの最適化
    function lazyLoad() {
        const lazyImages = document.querySelectorAll('img.lazy-load');
        // Intersection Observerをサポートしているブラウザの場合
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.add('loaded');
                        imageObserver.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });

            lazyImages.forEach(function(img) {
                imageObserver.observe(img);
            });
        } else {
            // フォールバック: すべての画像を読み込む
            lazyImages.forEach(function(img) {
                img.src = img.dataset.src;
                img.classList.add('loaded');
            });
        }
    }
    
    // モバイル端末向けのビデオ最適化
    const weddingVideo = document.getElementById('wedding-video');
    if (weddingVideo) {
        // 動画再生コントロール
        const playButton = document.querySelector('.play-button');
        const videoOverlay = document.querySelector('.video-overlay');
        
        if (playButton && videoOverlay) {
            playButton.addEventListener('click', function() {
                weddingVideo.play();
                videoOverlay.style.opacity = '0';
                setTimeout(() => {
                    videoOverlay.style.display = 'none';
                }, 500);
            });
            
            // モバイルの場合、自動再生を防ぐ
            weddingVideo.addEventListener('play', function() {
                videoOverlay.style.opacity = '0';
                setTimeout(() => {
                    videoOverlay.style.display = 'none';
                }, 500);
            });
            
            // 動画終了時にオーバーレイを再表示
            weddingVideo.addEventListener('ended', function() {
                videoOverlay.style.display = 'flex';
                setTimeout(() => {
                    videoOverlay.style.opacity = '1';
                }, 10);
            });
        }
    }
    
    // モバイルでのスムーズスクロール
    const allLinks = document.querySelectorAll('a[href^="#"]');
    allLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            const href = this.getAttribute('href');
            if (href !== "#") {
                e.preventDefault();
                const targetElement = document.querySelector(href);
                if (targetElement) {
                    // スムーズスクロール（モバイルでは少し早めに）
                    const duration = isMobileDevice ? 500 : 800;
                    const offsetTop = targetElement.getBoundingClientRect().top + window.pageYOffset;
                    
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            }
        });
    });
    
    // FAQ項目のアコーディオン機能
    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(item => {
        const question = item.querySelector('.faq-question');
        question.addEventListener('click', function() {
            // 現在開いている項目を閉じる
            const currentActive = document.querySelector('.faq-item.active');
            if (currentActive && currentActive !== item) {
                currentActive.classList.remove('active');
            }
            
            // クリックした項目を開閉
            item.classList.toggle('active');
        });
    });
    
    // 必要な関数を呼び出し
    lazyLoad();
    updateCountdown();
    initFormHandlers();
    
    // スクロール時のアニメーション
    window.addEventListener('scroll', function() {
        // モバイルの場合はスクロールイベントの頻度を制限
        if (!isMobileDevice || !window.scrollThrottleTimeout) {
            window.scrollThrottleTimeout = setTimeout(function() {
                animateOnScroll();
                window.scrollThrottleTimeout = null;
            }, isMobileDevice ? 100 : 50);
        }
    });
    
    // ページ読み込み時に一度実行
    animateOnScroll();
    
    // ナビゲーションの動作改善
    const mobileNav = document.getElementById('mobile-nav-toggle');
    if (mobileNav) {
        mobileNav.addEventListener('click', function(e) {
            e.preventDefault();
            document.body.classList.toggle('mobile-nav-active');
            
            // モバイルメニュー展開時はスクロール防止
            if (document.body.classList.contains('mobile-nav-active')) {
                document.body.classList.add('no-scroll');
            } else {
                document.body.classList.remove('no-scroll');
            }
            
            this.classList.toggle('active');
        });
        
        // メニュー項目クリック時の処理改善
        const navLinks = document.querySelectorAll('.mobile-nav a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                document.body.classList.remove('mobile-nav-active');
                document.body.classList.remove('no-scroll');
                mobileNav.classList.remove('active');
            });
        });
    }
}); 