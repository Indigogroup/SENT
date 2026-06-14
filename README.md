# Woo Print Stock Lists

Plugin WordPress/WooCommerce dodający zakładkę **„Drukuj stany"** w panelu admina, umożliwiającą generowanie list produktów z dodatnim stanem magazynowym.

---

## Wymagania

| Składnik | Minimalna wersja |
|---|---|
| WordPress | 5.8 |
| WooCommerce | 5.0 |
| PHP | 7.4 |
| PHP ZipArchive | dowolna (dla .xlsx) |

Rozszerzenie `ZipArchive` jest standardowo dostępne w PHP od wersji 5.2 i jest wymagane do generowania plików `.xlsx`.

---

## Instalacja

1. Pobierz katalog `woo-print-stock-lists` z tego repozytorium.
2. Spakuj go do pliku ZIP:
   ```bash
   zip -r woo-print-stock-lists.zip woo-print-stock-lists/
   ```
3. W panelu admina WordPress przejdź do **Wtyczki → Dodaj nową → Wyślij wtyczkę**.
4. Wgraj plik ZIP i aktywuj wtyczkę.
5. W menu bocznym WooCommerce pojawi się pozycja **Drukuj stany**.

---

## Użytkowanie

### Wybieranie kategorii

1. Przejdź do **WooCommerce → Drukuj stany**.
2. Zaznacz checkboxy przy interesujących Cię kategoriach.
   - Zaznaczenie kategorii nadrzędnej powoduje wyświetlenie jej podkategorii.
   - Możesz zaznaczyć tylko kategorię nadrzędną lub wybrane podkategorie.
3. Kliknij **„Generuj listę"**.

### Logika filtrowania

| Scenariusz | Wynik |
|---|---|
| Zaznaczona tylko kategoria nadrzędna | Produkty z tej kategorii **oraz wszystkich jej podkategorii** |
| Zaznaczona kategoria nadrzędna + wybrane podkategorie | Produkty **tylko z zaznaczonych podkategorii** |
| Kilka poziomów – zaznaczone pod-podkategorie | Produkty z **najgłębiej zaznaczonych** kategorii |

### Produkty wariantowe

Każdy wariant produktu zmiennego jest traktowany jako osobna pozycja na liście z własnym SKU, nazwą (nazwa rodzica + atrybuty) i stanem.

### Lista zawiera

- Wyłącznie produkty ze **stanem > 0**.
- Kolumny: **SKU**, **Nazwa**, **Stan**.
- Sumę łącznego stanu na końcu.

### Historia list

Wygenerowane listy widoczne są w sekcji **„Wygenerowane listy"** w tej samej zakładce. Tabela zawiera:

- **Data** – kiedy wygenerowano listę
- **Kategorie** – z jakich kategorii pochodzi lista
- **Pobierz** – przyciski **XLSX** i **PDF**
- **Działanie** – przycisk **„Usuń"**

Usunięcie rekordu kasuje też pliki z serwera.

---

## Bezpieczeństwo

- Wszystkie akcje wymagają uprawnienia `manage_woocommerce`.
- Formularze i żądania AJAX chronione noncami WordPress.
- Dane wejściowe sanitizowane (`absint`, `sanitize_text_field`).
- Dane wyjściowe escapowane (`esc_html`, `esc_attr`, `esc_url`).
- Pliki przechowywane w `wp-content/uploads/woo-psl/` z `.htaccess` blokującym bezpośredni dostęp HTTP.
- Pobieranie plików odbywa się przez bezpieczny endpoint (`admin-ajax.php`) po weryfikacji nonce i uprawnień.

---

## Struktura plików wtyczki

```
woo-print-stock-lists/
├── woo-print-stock-lists.php     # Główny plik wtyczki (nagłówek, bootstrap)
├── includes/
│   ├── class-db.php              # Operacje na bazie danych (tworzenie tabeli, CRUD)
│   ├── class-admin-menu.php      # Rejestracja menu, enqueue assets, renderowanie strony
│   ├── class-category-tree.php   # Budowanie drzewa kategorii, logika filtrowania
│   ├── class-product-query.php   # Zapytania o produkty z uwzględnieniem wariantów
│   ├── class-xlsx.php            # Generator .xlsx (Office Open XML, ZipArchive)
│   ├── class-pdf.php             # Generator .pdf (minimalny, bez zewnętrznych zależności)
│   ├── class-generator.php       # Orkiestrator: generuje pliki, zapisuje do DB
│   └── class-ajax.php            # Handlery AJAX: generate, delete, download
├── admin/
│   ├── views/
│   │   └── page-print-stock.php  # Szablon HTML strony admina
│   ├── css/
│   │   └── print-stock.css       # Style CSS panelu
│   └── js/
│       └── print-stock.js        # JavaScript: drzewo kategorii, AJAX
└── README.md                     # Ten plik
```

---

## Decyzje implementacyjne

### XLSX
Generowany bezpośrednio w formacie Office Open XML (`.xlsx`) przy użyciu wbudowanej klasy PHP `ZipArchive`. Brak zewnętrznych bibliotek. Plik zawiera: tytuł, dane kategorii i datę, nagłówki kolumn, wiersze danych i wiersz sumy.

### PDF
Zaimplementowany własny, minimalny generator PDF (klasa `Woo_PSL_Pdf`) bez żadnych zewnętrznych zależności. Obsługa polskich znaków diakrytycznych poprzez:
- Konwersję UTF-8 → ISO-8859-2 (polskie litery: ą, ć, ę, ł, ń, ó, ś, ź, ż)
- Niestandardowy słownik kodowania (`/Differences`) w obiekcie czcionki PDF mapujący bajty ISO-8859-2 na właściwe glify Helvetici

### Bezpieczeństwo plików
Pliki przechowywane w `uploads/woo-psl/` z `.htaccess` (`Deny from all`). Pobieranie wyłącznie przez `admin-ajax.php` po weryfikacji nonce i uprawnienia `manage_woocommerce`.

### Warianty produktów
`WC_Product_Variable::get_available_variations('objects')` zwraca obiekty wariantów. Każdy wariant z `stock > 0` trafia na listę jako osobna pozycja z pełną nazwą (rodzic + atrybuty wariantu).
