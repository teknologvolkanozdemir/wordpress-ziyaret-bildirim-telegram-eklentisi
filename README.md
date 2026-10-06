# WordPress Ziyaret Bildirim Telegram Eklentisi

WordPress sitenizin ön yüzüne yapılan ziyaretleri, Telegram botu ve sohbet bilgilerini kaydetmiş yöneticilere bildirir.

## Kurulum

1. Bu depodaki `wordpress-ziyaret-bildirim-telegram-eklentisi.php` dosyasını `/wp-content/plugins/wordpress-ziyaret-bildirim-telegram-eklentisi/` dizinine yükleyin.
2. WordPress yönetim panelinde **Eklentiler** bölümünden eklentiyi etkinleştirin.
3. Her yönetici, kendi **Kullanıcılar → Profil** sayfasına giderek Telegram bot tokenini ve chat ID değerini kaydetmelidir. Botu Telegram'da oluşturup sohbeti başlattıktan sonra bu bilgileri girin.
4. Ziyaret bildirimlerini durdurmak için profil sayfasındaki Telegram bağlantısını silme kutusunu işaretleyip profili kaydedin.

Yalnızca bot tokeni değiştirilmek isteniyorsa token alanına yeni değer girilebilir; boş bırakılan token korunur. Chat ID alanını boş kaydetmek o yöneticinin bildirimlerini devre dışı bırakır. Bildirimler, ön yüz isteği sırasında arka planda gönderilir.

## Erişilebilirlik ve ziyaretçi verileri

Ayarlar WordPress'in standart profil formu içinde, görünür etiketler ve açıklamalarla sunulur; tema tarafına ek arayüz veya JavaScript eklenmez.

Bildirim her ön yüz isteğinde site adı, sorgu dizesi çıkarılmış sayfa adresi, ziyaretçinin IP adresi ve sunucu tarih/saatini içerir. Bu bilgilerin Telegram'a aktarımını gizlilik politikanızda belirtin ve yürürlükteki veri koruma gerekliliklerine uygun şekilde kullanın. Telegram API'sine bağlantı HTTPS üzerinden yapılır.
