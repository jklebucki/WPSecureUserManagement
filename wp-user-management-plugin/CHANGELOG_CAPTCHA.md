# Zmiany w systemie walidacji CAPTCHA

## Data: 19 października 2025

### Wprowadzone zmiany

#### 1. Automatyczne odświeżanie CAPTCHA
- CAPTCHA jest teraz automatycznie odświeżana przy wejściu na stronę rejestracji
- Kod jest generowany asynchronicznie przez AJAX bez przeładowania strony

#### 2. Walidacja CAPTCHA przed wysłaniem formularza
System teraz weryfikuje poprawność kodu CAPTCHA **przed** wysłaniem formularza do serwera:

- **Walidacja on-blur**: Kod jest sprawdzany gdy użytkownik opuści pole (blur event)
- **Walidacja przed submit**: Formularz nie zostanie wysłany dopóki CAPTCHA nie będzie poprawna
- **Wizualne informacje zwrotne**: 
  - Czerwone obramowanie i komunikat błędu dla nieprawidłowego kodu
  - Zielone obramowanie i potwierdzenie dla poprawnego kodu
- **Auto-scroll**: Przy błędzie strona automatycznie przewija się do pola CAPTCHA
- **Blokada przycisku**: Przycisk submit jest blokowany podczas weryfikacji

#### 3. Nowe funkcje AJAX

##### `wpum_ajax_verify_captcha`
Endpoint do weryfikacji kodu CAPTCHA bez usuwania tokenu (token jest usuwany dopiero przy finalnej walidacji serwerowej).

**Parametry:**
- `token` - Token CAPTCHA
- `code` - Kod wprowadzony przez użytkownika
- `nonce` - Nonce bezpieczeństwa

**Odpowiedź:**
```json
// Sukces
{
  "success": true,
  "data": {
    "message": "Code is correct"
  }
}

// Błąd
{
  "success": false,
  "data": {
    "message": "Incorrect code"
  }
}
```

#### 4. Nowe elementy UI

##### Przycisk odświeżania
- Dodano przycisk z ikoną odświeżania obok kodu CAPTCHA
- Animacja rotacji podczas ładowania nowego kodu
- Tooltip z tekstem "Odśwież kod"

##### Komunikaty walidacji
- **Błąd**: Czerwone obramowanie + komunikat błędu w czerwonym polu
- **Sukces**: Zielone obramowanie + komunikat sukcesu w zielonym polu

#### 5. Zmodyfikowane pliki

1. **includes/captcha.php**
   - Dodano `wpum_ajax_verify_captcha()` - weryfikacja CAPTCHA przez AJAX
   - Zmodyfikowano logikę aby token nie był usuwany podczas pre-walidacji

2. **includes/register-form.php**
   - Dodano enqueue dla nowego pliku JavaScript
   - Dodano ID do elementów CAPTCHA dla łatwiejszej manipulacji przez JS
   - Dodano przycisk odświeżania z ikoną SVG

3. **includes/register-form.js** (NOWY)
   - Automatyczne odświeżanie CAPTCHA przy załadowaniu
   - Walidacja CAPTCHA przed wysłaniem formularza
   - Obsługa przycisk odświeżania
   - Walidacja on-blur dla lepszego UX
   - Wizualne feedback dla użytkownika

4. **includes/register-form.css**
   - Style dla stanów sukcesu/błędu pola input
   - Style dla komunikatów błędów i sukcesów
   - Style dla przycisku odświeżania
   - Animacja rotacji dla ikony odświeżania

5. **languages/wp-user-management-plugin-pl_PL.po**
   - Dodano tłumaczenia: "Proszę wprowadzić kod", "Kod wygasł. Proszę odświeżyć", "Nieprawidłowy kod", "Kod jest poprawny", "Odśwież kod"

6. **languages/wp-user-management-plugin-en_US.po**
   - Dodano angielskie wersje nowych komunikatów

#### 6. Przepływ działania

```
1. Użytkownik wchodzi na stronę rejestracji
   ↓
2. JavaScript automatycznie wywołuje AJAX do wygenerowania nowego kodu CAPTCHA
   ↓
3. Użytkownik wypełnia formularz
   ↓
4. Użytkownik wprowadza kod CAPTCHA
   ↓
5. Przy opuszczeniu pola (blur) - automatyczna weryfikacja
   ↓
6. Jeśli kod poprawny → zielone obramowanie + checkmark
   Jeśli kod błędny → czerwone obramowanie + komunikat błędu
   ↓
7. Użytkownik klika "Zarejestruj"
   ↓
8. JavaScript sprawdza czy CAPTCHA została zweryfikowana
   ↓
9a. Jeśli TAK → formularz jest wysyłany do serwera
9b. Jeśli NIE → wywołanie weryfikacji AJAX
    ↓
    Jeśli poprawna → wysłanie formularza
    Jeśli błędna → zablokowanie wysyłki + scroll do pola
```

#### 7. Zalety nowego rozwiązania

✅ **Lepsza wydajność serwera** - niepoprawne CAPTCHA nie generują requestów POST
✅ **Lepsze UX** - natychmiastowa informacja zwrotna dla użytkownika
✅ **Mniej błędów** - użytkownik nie traci wypełnionych danych przy błędnym kodzie
✅ **Bezpieczeństwo** - walidacja odbywa się zarówno na kliencie jak i serwerze
✅ **Dostępność** - scroll do pola błędu, wyraźne komunikaty

#### 8. Kompatybilność

- WordPress 5.0+
- jQuery (wbudowane w WordPress)
- Współpracuje z istniejącą walidacją serwerową
- Nie wymaga żadnych zmian w bazie danych

#### 9. Testowanie

Aby przetestować:
1. Wejdź na stronę rejestracji
2. Sprawdź czy kod CAPTCHA się automatycznie odświeża
3. Wprowadź błędny kod i sprawdź czy pojawia się komunikat błędu
4. Wprowadź poprawny kod i sprawdź czy pojawia się zielone obramowanie
5. Spróbuj wysłać formularz z błędnym kodem - powinien być zablokowany
6. Kliknij przycisk odświeżania i sprawdź czy pojawia się nowy kod
