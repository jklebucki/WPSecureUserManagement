# ⚠️ WAŻNE: Instrukcja czyszczenia cache

## Problem na obrazie:
- Przycisk jest NIEBIESKI zamiast ZIELONEGO
- Layout może być nierówny
- CSS się nie zaktualizował

## ✅ Rozwiązanie - Wyczyść cache:

### 1️⃣ Wyczyść cache przeglądarki (CTRL+SHIFT+R lub CMD+SHIFT+R)

**Chrome/Edge/Firefox:**
- Windows: `CTRL + SHIFT + R`
- Mac: `CMD + SHIFT + R`

LUB otwórz narzędzia deweloperskie (F12) i kliknij prawym na ikonę odświeżania → "Wyczyść pamięć podręczną i odśwież"

### 2️⃣ Wyczyść cache WordPress

Jeśli używasz wtyczki do cache (WP Super Cache, W3 Total Cache, itp.):
- Wejdź do panelu WordPress
- Znajdź opcję "Wyczyść cache" / "Clear cache"
- Kliknij

### 3️⃣ Wyczyść cache serwera (jeśli masz)

Niektóre serwery (CloudFlare, Varnish) cachują pliki CSS.

### 4️⃣ Sprawdź czy plik CSS się zaktualizował

Otwórz w przeglądarce bezpośrednio:
```
https://twoja-strona.pl/wp-content/plugins/wp-user-management-plugin/includes/register-form.css
```

I sprawdź czy zawiera:
```css
background: #46b450 !important;
```

## 🔧 Co zostało zmienione w kodzie:

### 1. Dodano wersjonowanie plików (wymuś przeładowanie)
```php
wp_enqueue_style('wpum-register-form', plugin_dir_url(__FILE__) . 'register-form.css', array(), '2.0.0');
```

### 2. Dodano `!important` do wszystkich kluczowych styli
- Wymusza nadpisanie innych styli
- Zapewnia zgodność z innymi pluginami/motywami

### 3. Doprecyzowano wszystkie wymiary
```css
.wpum-captcha-refresh {
    background: #46b450 !important;        /* ZIELONY */
    flex: 0 0 44px !important;            /* Stała szerokość */
    border-radius: 0 4px 4px 0 !important; /* Prawe zaokrąglenia */
}
```

### 4. Wymuszone flexbox w poziomie
```css
.wpum-captcha-row {
    display: flex !important;
    flex-direction: row !important;        /* Poziomo! */
    flex-wrap: nowrap !important;          /* Bez zawijania */
}
```

## 🎯 Oczekiwany rezultat po wyczyszczeniu cache:

```
┌──────────────┬───┐        ┌──────────────────┐
│   6YNUJ7     │ 🔄│ 10px   │  [____________]  │
│   (szary)    │(ZIE│        │     (input)      │
└──────────────┴LON┘        └──────────────────┘
                Y
```

- Kod CAPTCHA w szarym polu
- Przycisk ZIELONY doklejony do kodu
- Input po prawej stronie z odstępem
- Wszystko w jednej linii, równo

## 🚨 Jeśli nadal nie działa:

1. Sprawdź konsolę przeglądarki (F12 → Console) czy są błędy CSS
2. Sprawdź czy plik CSS został poprawnie zapisany na serwerze
3. Spróbuj w trybie incognito/prywatnym
4. Sprawdź czy motyw WordPress nie nadpisuje styli

## 📞 Debugging:

Dodaj do przeglądarki i sprawdź w konsoli:
```javascript
console.log(getComputedStyle(document.querySelector('.wpum-captcha-refresh')).backgroundColor);
// Powinno zwrócić: rgb(70, 180, 80) - to jest #46b450
```
