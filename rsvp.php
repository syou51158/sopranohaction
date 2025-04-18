<div class="form-group">
    <label for="dietary">食事制限やアレルギー</label>
    <textarea id="dietary" name="dietary" rows="2" placeholder="ご本人様のアレルギーや食事制限をご記入ください"></textarea>
</div>

<div class="form-group">
    <label for="postal_code">郵便番号</label>
    <div class="postal-code-input">
        <input type="text" id="postal_code" name="postal_code" placeholder="例: 123-4567" maxlength="8">
        <button type="button" id="address-lookup-btn">住所検索</button>
    </div>
</div>

<div class="form-group">
    <label for="address">住所</label>
    <textarea id="address" name="address" rows="2" placeholder="ご住所を入力してください"></textarea>
</div>

<div class="form-group">
    <label for="message">メッセージ</label>
    <textarea id="message" name="message" rows="4" placeholder="新郎新婦へのメッセージがあればご記入ください"></textarea>
</div>

<script>
// 郵便番号から住所検索機能
document.getElementById('address-lookup-btn').addEventListener('click', function() {
    const postalCode = document.getElementById('postal_code').value.replace(/[^\d]/g, '');
    
    if (postalCode.length !== 7) {
        alert('7桁の郵便番号を入力してください');
        return;
    }
    
    fetch(`https://zipcloud.ibsnet.co.jp/api/search?zipcode=${postalCode}`)
        .then(response => response.json())
        .then(data => {
            if (data.status === 200 && data.results) {
                const result = data.results[0];
                const address = `${result.address1}${result.address2}${result.address3}`;
                document.getElementById('address').value = address;
            } else {
                alert('郵便番号に該当する住所が見つかりませんでした');
            }
        })
        .catch(error => {
            console.error('住所検索エラー:', error);
            alert('住所の検索中にエラーが発生しました');
        });
});

// 郵便番号のフォーマット
document.getElementById('postal_code').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^\d]/g, '');
    if (value.length > 3) {
        value = value.slice(0, 3) + '-' + value.slice(3);
    }
    e.target.value = value;
});
</script> 