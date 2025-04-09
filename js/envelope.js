// 封筒専用のJavaScript機能
document.addEventListener('DOMContentLoaded', function() {
    // 要素の取得
    const envelope = document.querySelector('.envelope');
    const envelopeContainer = document.querySelector('.envelope-container');
    const invitationContent = document.querySelector('.invitation-content');
    const choiceScreen = document.querySelector('.choice-screen');
    const choiceContent = document.querySelector('.choice-content');
    const celebrationEffects = document.querySelector('.celebration-effects');
    
    // 重複処理防止用のフラグ
    let openedEnvelope = false;

    // 封筒が存在する場合の処理
    if (envelope && envelopeContainer) {
        console.log('封筒演出の初期化');
        
        // 封筒の内部要素を追加
        setupEnvelopeElements();
        
        // 花びらのアニメーションをランダム化
        randomizePetals();
        
        // セレブレーションエフェクトを準備
        setupCelebrationEffects();
        
        // 封筒の3D効果（デスクトップのみ）
        setupEnvelope3DEffect();
        
        // 封筒クリックイベント
        setupEnvelopeClickEvent();
    } else {
        console.log('封筒要素が見つかりません');
        
        // 封筒がない場合はコンテンツを表示
        if (invitationContent) {
            invitationContent.classList.remove('hide');
        }
    }
    
    // 選択肢のクリックイベント
    setupChoiceButtonsEvents();
    
    // 封筒内の要素をセットアップ
    function setupEnvelopeElements() {
        // 内側のパターン要素を追加
        if (!envelope.querySelector('.envelope-inner')) {
            const envelopeInner = document.createElement('div');
            envelopeInner.classList.add('envelope-inner');
            envelope.appendChild(envelopeInner);
            
            // パターン要素を追加
            const envelopePattern = document.createElement('div');
            envelopePattern.classList.add('envelope-pattern');
            envelopeInner.appendChild(envelopePattern);
        }
        
        // ワックスシールが存在しない場合は追加
        if (!envelope.querySelector('.wax-seal')) {
            const envelopeFlap = envelope.querySelector('.envelope-flap');
            if (envelopeFlap) {
                const waxSeal = document.createElement('div');
                waxSeal.classList.add('wax-seal');
                
                // テクスチャと反射光効果の要素を追加
                const waxSealTexture = document.createElement('div');
                waxSealTexture.classList.add('wax-seal-texture');
                waxSeal.appendChild(waxSealTexture);
                
                const waxSealHighlight = document.createElement('div');
                waxSealHighlight.classList.add('wax-seal-highlight');
                waxSeal.appendChild(waxSealHighlight);
                
                envelopeFlap.appendChild(waxSeal);
            }
        }
        
        // タップ指示を追加（デバイスによって変更）
        const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0);
        const instructionText = isTouchDevice ? 'タップして開く' : 'クリックして開く';
        
        const envelopeContent = envelope.querySelector('.envelope-content');
        if (envelopeContent) {
            let tapInstruction = envelopeContent.querySelector('.tap-instruction');
            if (!tapInstruction) {
                tapInstruction = document.createElement('p');
                tapInstruction.classList.add('tap-instruction');
                tapInstruction.textContent = instructionText;
                envelopeContent.appendChild(tapInstruction);
            } else if (!tapInstruction.textContent.trim()) {
                // テキストが空の場合のみ設定
                tapInstruction.textContent = instructionText;
            }
            
            // 既存のタップインストラクションがあれば削除（重複防止）
            const existingTapInstructions = envelopeContent.querySelectorAll('.tap-instruction');
            if (existingTapInstructions.length > 1) {
                for (let i = 1; i < existingTapInstructions.length; i++) {
                    existingTapInstructions[i].remove();
                }
            }
        }
        
        // 選択コンテンツを追加（なければ）
        if (!envelope.querySelector('.choice-content') && choiceScreen) {
            const choiceContentElement = document.createElement('div');
            choiceContentElement.classList.add('choice-content');
            
            // ヘッダー部分
            const headerElement = document.createElement('div');
            headerElement.classList.add('choice-header');
            
            // ゲスト名を取得
            const guestNameElement = document.querySelector('.guest-name');
            const guestName = guestNameElement ? guestNameElement.textContent : '親愛なるゲスト様へ';
            
            headerElement.innerHTML = `
                <h2>${guestName}</h2>
                <p>下記のいずれかをお選びください</p>
            `;
            
            // ボタン部分
            const buttonsElement = document.createElement('div');
            buttonsElement.classList.add('choice-buttons');
            
            // 招待状ボタン
            const invitationButton = document.createElement('div');
            invitationButton.classList.add('choice-button', 'invitation-button');
            invitationButton.setAttribute('data-target', 'invitation');
            invitationButton.innerHTML = `<i class="fas fa-envelope-open-text"></i> 招待状`;
            
            buttonsElement.appendChild(invitationButton);
            
            // 付箋ボタンがあるか確認
            const fusenCard = document.querySelector('.choice-fusen-card');
            if (fusenCard) {
                const fusenUrl = fusenCard.getAttribute('data-url') || fusenCard.getAttribute('href');
                if (fusenUrl && fusenUrl !== '#') {
                    const fusenButton = document.createElement('div');
                    fusenButton.classList.add('choice-button', 'fusen-button');
                    fusenButton.setAttribute('data-url', fusenUrl);
                    fusenButton.innerHTML = `<i class="fas fa-sticky-note"></i> 付箋`;
                    
                    buttonsElement.appendChild(fusenButton);
                    console.log('付箋ボタンを作成:', fusenUrl);
                }
            }
            
            // 要素を追加
            choiceContentElement.appendChild(headerElement);
            choiceContentElement.appendChild(buttonsElement);
            
            envelope.appendChild(choiceContentElement);
        }
    }
    
    // 花びらのアニメーションをランダム化
    function randomizePetals() {
        const petals = document.querySelectorAll('.petal');
        petals.forEach(petal => {
            const delay = Math.random() * 8; // 0-8秒のランダム遅延
            petal.style.animationDelay = `${delay}s`;
            petal.style.opacity = '1'; // 初めから表示
        });
    }
    
    // セレブレーションエフェクトを準備
    function setupCelebrationEffects() {
        if (celebrationEffects) {
            const hearts = celebrationEffects.querySelectorAll('.heart');
            const sparkles = celebrationEffects.querySelectorAll('.sparkle');
            
            // ハートアニメーションのランダム化
            hearts.forEach(heart => {
                const delay = 0.5 + Math.random() * 1.2; // 0.5-1.7秒のランダム遅延
                heart.style.animationDelay = `${delay}s`;
                
                // サイズもランダム化
                const size = 25 + Math.random() * 15;
                heart.style.width = `${size}px`;
                heart.style.height = `${size}px`;
            });
            
            // キラキラアニメーションのランダム化
            sparkles.forEach(sparkle => {
                const delay = 0.6 + Math.random() * 1.5; // 0.6-2.1秒のランダム遅延
                sparkle.style.animationDelay = `${delay}s`;
                
                // サイズもランダム化
                const size = 15 + Math.random() * 15;
                sparkle.style.width = `${size}px`;
                sparkle.style.height = `${size}px`;
                
                // 位置もランダム化（中央寄りに）
                sparkle.style.left = `${10 + Math.random() * 80}%`;
                sparkle.style.top = `${10 + Math.random() * 80}%`;
            });
        }
    }
    
    // 封筒の3D効果
    function setupEnvelope3DEffect() {
        // タッチデバイスでなければ3D効果を適用
        const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0);
        if (!isTouchDevice) {
            envelope.addEventListener('mousemove', function(e) {
                if (this.classList.contains('opening')) return;
                
                const rect = this.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const y = e.clientY - rect.top;
                
                // 中心からの距離を計算して傾きを決定
                const centerX = rect.width / 2;
                const centerY = rect.height / 2;
                
                // 傾きの計算（中心からの距離に応じて）
                const tiltX = ((y - centerY) / centerY) * 5; // 最大5度
                const tiltY = ((x - centerX) / centerX) * 5; // 最大5度
                
                // 封筒に傾きを適用
                this.style.transform = `perspective(1500px) rotateX(${tiltX}deg) rotateY(${-tiltY}deg)`;
                
                // ワックスシールの特別な効果
                const waxSeal = this.querySelector('.wax-seal');
                if (waxSeal) {
                    // マウスとシールの距離を計算
                    const sealRect = waxSeal.getBoundingClientRect();
                    const sealCenterX = sealRect.left + sealRect.width / 2;
                    const sealCenterY = sealRect.top + sealRect.height / 2;
                    
                    // マウスとシールの距離を計算
                    const deltaX = e.clientX - sealCenterX;
                    const deltaY = e.clientY - sealCenterY;
                    const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
                    
                    // マウスが近いほど強い効果、遠いほど弱い効果
                    // 距離によって効果の強さを調整（200pxを最大距離とする）
                    const maxDistance = 200;
                    const effectStrength = Math.max(0, 1 - (distance / maxDistance));
                    
                    // 光の反射計算（マウスの位置に応じて変化）
                    const normalizedX = deltaX / maxDistance; // -1から1の範囲
                    const normalizedY = deltaY / maxDistance; // -1から1の範囲
                    
                    // 非常に微妙な傾き（高級感を保つため大きく動かさない）
                    const maxTilt = 1.5; // 最大1.5度の傾き
                    const sealTiltX = normalizedY * maxTilt * effectStrength;
                    const sealTiltY = -normalizedX * maxTilt * effectStrength;
                    
                    // 立体感を保ちながら、非常に微妙な動きだけを適用
                    waxSeal.style.transform = `translate(-50%, -50%) rotateX(${sealTiltX}deg) rotateY(${sealTiltY}deg)`;
                    
                    // ハイライト効果（マウスの位置に合わせて動く）
                    const waxSealHighlight = waxSeal.querySelector('.wax-seal-highlight');
                    if (waxSealHighlight) {
                        // マウスに合わせてハイライトを移動（非常に微妙に）
                        const highlightX = 50 - (normalizedX * 15 * effectStrength);
                        const highlightY = 50 - (normalizedY * 15 * effectStrength);
                        waxSealHighlight.style.background = `
                            radial-gradient(
                                circle at ${highlightX}% ${highlightY}%, 
                                rgba(255, 255, 255, ${0.4 + (0.1 * effectStrength)}) 0%, 
                                rgba(255, 255, 255, 0) 60%, 
                                rgba(0, 0, 0, ${0.05 * effectStrength}) 100%
                            )
                        `;
                        // マウスの方向に少し強くなる
                        waxSealHighlight.style.opacity = 0.5 + (0.2 * effectStrength);
                    }
                    
                    // テクスチャの効果も動的に変化
                    const waxSealTexture = waxSeal.querySelector('.wax-seal-texture');
                    if (waxSealTexture) {
                        // マウスの位置に応じてテクスチャの見え方を微妙に変化
                        waxSealTexture.style.opacity = 0.25 + (0.15 * effectStrength);
                        waxSealTexture.style.backgroundImage = `
                            radial-gradient(
                                circle at ${45 - (normalizedX * 10 * effectStrength)}% ${45 - (normalizedY * 10 * effectStrength)}%, 
                                rgba(255, 255, 255, 0.6) 0%, 
                                transparent 70%
                            ),
                            radial-gradient(
                                circle at ${55 + (normalizedX * 10 * effectStrength)}% ${55 + (normalizedY * 10 * effectStrength)}%, 
                                rgba(0, 0, 0, 0.2) 0%, 
                                transparent 70%
                            )
                        `;
                    }
                }
            });
            
            // マウスが離れたときに元に戻す
            envelope.addEventListener('mouseleave', function() {
                if (this.classList.contains('opening')) return;
                
                this.style.transform = '';
                
                const waxSeal = this.querySelector('.wax-seal');
                if (waxSeal) {
                    // 元のスタイルに戻す（滑らかに）
                    waxSeal.style.transform = 'translate(-50%, -50%)';
                    
                    // ハイライトを元に戻す
                    const waxSealHighlight = waxSeal.querySelector('.wax-seal-highlight');
                    if (waxSealHighlight) {
                        waxSealHighlight.style.background = `
                            linear-gradient(135deg, rgba(255, 255, 255, 0.4) 0%, rgba(255, 255, 255, 0) 50%, rgba(0, 0, 0, 0.1) 100%)
                        `;
                        waxSealHighlight.style.opacity = '0.6';
                    }
                    
                    // テクスチャを元に戻す
                    const waxSealTexture = waxSeal.querySelector('.wax-seal-texture');
                    if (waxSealTexture) {
                        waxSealTexture.style.opacity = '0.35';
                        waxSealTexture.style.backgroundImage = `
                            radial-gradient(circle at 45% 45%, rgba(255, 255, 255, 0.6) 0%, transparent 70%),
                            radial-gradient(circle at 55% 55%, rgba(0, 0, 0, 0.2) 0%, transparent 70%)
                        `;
                    }
                }
            });
        }
    }
    
    // 封筒クリックイベント
    function setupEnvelopeClickEvent() {
        // 重複クリック防止
        let envelopeClicked = false;
        
        envelope.addEventListener('click', function() {
            if (envelopeClicked) return;
            envelopeClicked = true;
            
            // 3D効果を削除
            this.style.transform = '';
            
            console.log('封筒がクリックされました');
            
            // 効果音を再生（オプション）
            try {
                const openSound = new Audio('sounds/envelope_open.mp3');
                openSound.volume = 0.5;
                openSound.play().catch(e => console.log('音声再生エラー:', e));
            } catch (e) {
                console.log('音声機能は利用できません');
            }
            
            // スクロール位置を記憶
            const scrollPosition = window.scrollY;
            
            // エンベロープコンテナが画面中央に表示されるようにスクロール
            const containerRect = envelopeContainer.getBoundingClientRect();
            const targetPosition = containerRect.top + window.scrollY - (window.innerHeight - containerRect.height) / 2;
            
            // スムーズにスクロール
            window.scrollTo({
                top: targetPosition,
                behavior: 'smooth'
            });
            
            // 封筒アニメーション開始
            this.classList.add('opening');
            
            // ワックスシールのアニメーション
            const waxSeal = this.querySelector('.wax-seal');
            if (waxSeal) {
                waxSeal.style.boxShadow = '';
            }
            
            // 封筒が開いたあとの処理
            setTimeout(() => {
                // 選択コンテンツを表示
                const choiceContent = envelope.querySelector('.choice-content');
                if (choiceContent) {
                    choiceContent.classList.add('visible');
                } else {
                    // 選択コンテンツがなければ従来の処理
                    handleTraditionalOpen();
                }
            }, 1800); // 封筒アニメーション時間
        });
    }
    
    // 従来の開封処理（フォールバック）
    function handleTraditionalOpen() {
        console.log('従来の開封処理を実行します');
        
        // 封筒コンテナをフェードアウト
        envelopeContainer.style.opacity = '0';
        envelopeContainer.style.transition = 'opacity 0.5s ease';
        
        // フェードアウト完了後に処理を実行
        setTimeout(() => {
            // 封筒コンテナを非表示に設定
            envelopeContainer.classList.add('hide');
            
            // 適切な画面を表示
            if (choiceScreen) {
                // 選択画面のスタイルを設定
                choiceScreen.style.display = 'flex';
                choiceScreen.style.flexDirection = 'column';
                choiceScreen.style.opacity = '0';
                choiceScreen.classList.remove('hide');
                
                // 強制リフロー
                void choiceScreen.offsetWidth;
                
                // 選択画面をフェードイン
                choiceScreen.style.transition = 'opacity 0.4s ease';
                choiceScreen.style.opacity = '1';
                
                // カードをアニメーションで表示
                const cards = choiceScreen.querySelectorAll('.choice-card');
                cards.forEach((card, index) => {
                    card.style.transitionDelay = `${100 + (index * 50)}ms`;
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                });
            } else if (invitationContent) {
                // 招待状コンテンツを表示準備
                invitationContent.style.display = 'block';
                invitationContent.style.opacity = '0';
                invitationContent.classList.remove('hide');
                
                // 強制リフロー
                void invitationContent.offsetWidth;
                
                // フェードイン
                invitationContent.style.transition = 'opacity 0.4s ease';
                invitationContent.style.opacity = '1';
                
                // スクロールを許可
                document.body.style.overflow = 'auto';
            }
        }, 500); // 封筒フェードアウトの時間
    }
    
    // 選択ボタンのイベント設定
    function setupChoiceButtonsEvents() {
        // 招待状ボタンのクリックイベント
        document.addEventListener('click', function(e) {
            const invitationButton = e.target.closest('.invitation-button');
            if (invitationButton) {
                console.log('招待状ボタンがクリックされました');
                
                // 選択コンテンツ非表示
                const choiceContent = envelope.querySelector('.choice-content');
                if (choiceContent) {
                    choiceContent.classList.remove('visible');
                }
                
                // 封筒コンテナも非表示
                envelope.style.display = 'none';
                envelopeContainer.style.opacity = '0';
                envelopeContainer.style.transition = 'opacity 0.5s ease';
                
                // フェードアウト完了後の処理
                setTimeout(() => {
                    envelopeContainer.classList.add('hide');
                    
                    // 招待状表示
                    if (invitationContent) {
                        invitationContent.classList.remove('hide');
                    }
                }, 500);
            }
            
            // 付箋ボタン
            if (e.target.closest('.fusen-button')) {
                const button = e.target.closest('.fusen-button');
                const fusenUrl = button.getAttribute('data-url');
                
                if (fusenUrl) {
                    console.log('付箋が選択されました:', fusenUrl);
                    window.location.href = fusenUrl;
                }
            }
            
            // 従来の選択カード
            const invitationCard = e.target.closest('.choice-invitation-card');
            if (invitationCard) {
                console.log('招待状カードがクリックされました');
                
                // フェードアウト
                choiceScreen.style.opacity = '0';
                choiceScreen.style.transition = 'opacity 0.3s ease';
                
                setTimeout(() => {
                    choiceScreen.classList.add('hide');
                    choiceScreen.style.display = 'none';
                    
                    // 招待状を表示
                    invitationContent.style.display = 'block';
                    void invitationContent.offsetWidth;
                    invitationContent.classList.remove('hide');
                    document.body.style.overflow = 'auto';
                }, 300);
            }
            
            // 付箋カード
            const fusenCard = e.target.closest('.choice-fusen-card');
            if (fusenCard) {
                console.log('付箋カードがクリックされました');
                const url = fusenCard.getAttribute('data-url');
                if (url) {
                    window.location.href = url;
                }
            }
        });
    }
    
    // クリック/タップエフェクト - 全ページ共通
    document.addEventListener('click', function(e) {
        const clickEffect = document.createElement('div');
        clickEffect.classList.add('click-effect');
        
        // クリック位置を設定
        clickEffect.style.left = e.pageX + 'px';
        clickEffect.style.top = e.pageY + 'px';
        
        // サイズをランダムに
        const size = 50 + Math.random() * 100;
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
        }, 1000);
    });

    // 封筒を開く処理
    function openEnvelope(e) {
        // すでに開封中または開封済みの場合は処理しない
        if (envelope.classList.contains('opening') || openedEnvelope) {
            return;
        }
        
        // タップ/クリックイベントのデフォルト動作を停止
        e.preventDefault();
        
        // 念のためのダブルタップ防止
        openedEnvelope = true;
        
        console.log('封筒を開きます...');
        
        // 封筒を開く前にスタイルを調整
        envelope.style.transform = 'none'; // 3D効果をリセット
        
        // スマホでのパフォーマンスを考慮して短い遅延で開封アニメーション実行
        setTimeout(() => {
            envelope.classList.add('opening');
            
            // アニメーション完了後の処理をJavaScript側で管理
            setTimeout(() => {
                completeEnvelopeOpen();
            }, 1500); // スマホではアニメーション時間を短くする
        }, 10);
    }

    // 封筒開封完了処理（アニメーション後に呼び出される）
    function completeEnvelopeOpen() {
        console.log('封筒開封完了処理を実行します');
        
        // 選択コンテンツを表示
        const choiceContent = envelope.querySelector('.choice-content');
        if (choiceContent) {
            choiceContent.classList.add('visible');
        } else {
            // 選択コンテンツがなければ従来の処理
            handleTraditionalOpen();
        }
    }

    // タッチデバイスの検出を改善
    const isTouchDevice = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0) || (navigator.msMaxTouchPoints > 0);
    
    // イベントリスナの追加（タッチデバイスとそれ以外で分ける）
    function addEnvelopeEventListeners() {
        if (isTouchDevice) {
            console.log('タッチデバイスが検出されました');
            
            // タッチデバイス用のイベントリスナ
            envelope.addEventListener('touchstart', handleTouchStart, { passive: false });
            envelope.addEventListener('touchend', handleTouchEnd, { passive: false });
            
            // iOS Safariでのイベント処理を改善
            document.addEventListener('gesturestart', function(e) {
                e.preventDefault(); // ピンチズームを防止
            }, { passive: false });
        } else {
            console.log('通常のクリックデバイスです');
            // 非タッチデバイス用
            envelope.addEventListener('click', openEnvelope);
            
            // マウスオーバー効果
            setupEnvelope3DEffect();
        }
    }
    
    // タッチイベント用変数
    let touchStartTime = 0;
    let touchEndTime = 0;
    const maxTouchDuration = 300; // タップと判定する最大時間（ミリ秒）
    
    // タッチ開始イベントハンドラ
    function handleTouchStart(e) {
        // タップ判定用の時間記録
        touchStartTime = new Date().getTime();
    }
    
    // タッチ終了イベントハンドラ
    function handleTouchEnd(e) {
        // タップ時間を計測
        touchEndTime = new Date().getTime();
        const touchDuration = touchEndTime - touchStartTime;
        
        // 短いタップと判定された場合に封筒を開く
        if (touchDuration < maxTouchDuration) {
            openEnvelope(e);
        }
    }
}); 