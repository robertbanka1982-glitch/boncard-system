<?php
// ROZPOCZĘCIE SESJI - musi być na samym początku pliku!
session_start();

echo "<h1>Centralny Panel Boncard v4.0</h1>";

$host = 'localhost';
$db   = 'boncard_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);

    // --- SCENARIUSZ 1: WYLOGOWANIE ---
    if (isset($_POST['akcja_wyloguj'])) {
        session_destroy(); // Kasujemy pamięć sesji
        header("Location: index.html"); // Przekierowujemy z powrotem do logowania
        exit();
    }

    // --- SCENARIUSZ 2: UŻYTKOWNIK KLIKNĄŁ "KUP KAWĘ" ---
    if (isset($_POST['akcja_kup_kawe'])) {
        // Pobieramy numer karty z pamięci sesji, a nie z formularza!
        $karta = $_SESSION['zalogowany_uzytkownik'];
        $koszt = 50;

        $sql = "SELECT punkty FROM karty WHERE numer_karty = :karta";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['karta' => $karta]);
        $klient = $stmt->fetch();

        if ($klient && $klient['punkty'] >= $koszt) {
            $update_sql = "UPDATE karty SET punkty = punkty - :koszt WHERE numer_karty = :karta";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute(['koszt' => $koszt, 'karta' => $karta]);

            echo "<p style='color: green; font-weight: bold;'>TRANSAKCJA UDANA! Zakupiono kawę za 50 punktów.</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>BŁĄD: Brak wystarczających środków na karcie!</p>";
        }
    }
    
    // --- SCENARIUSZ 3: UŻYTKOWNIK KLIKNĄŁ "ZALOGUJ SIĘ" ---
    elseif (isset($_POST['akcja_zaloguj'])) {
        $karta = $_POST['numer_karty'] ?? '';
        $pin   = $_POST['pin'] ?? '';

        $sql = "SELECT * FROM karty WHERE numer_karty = :karta AND pin = :pin";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['karta' => $karta, 'pin' => $pin]);
        $klient = $stmt->fetch();

        if ($klient) {
            // ZAPISUJEMY NUMER KARTY W PAMIĘCI SESJI SERWERA
            $_SESSION['zalogowany_uzytkownik'] = $klient['numer_karty'];
        } else {
            echo "<p style='color: red; font-weight: bold;'>BŁĄD: Niepoprawny numer karty lub PIN!</p>";
            echo "<p><a href='index.html'>Wróć i spróbuj ponownie</a></p>";
            exit();
        }
    } 
    
    // --- SCENARIUSZ 4: UŻYTKOWNIK KLIKNĄŁ "ZAREJESTRUJ" ---
    elseif (isset($_POST['akcja_zarejestruj'])) {
        $karta = $_POST['numer_karty'] ?? '';
        $pin   = $_POST['pin'] ?? '';

        $sprawdz_sql = "SELECT * FROM karty WHERE numer_karty = :karta";
        $stmt = $pdo->prepare($sprawdz_sql);
        $stmt->execute(['karta' => $karta]);
        
        if ($stmt->fetch()) {
            echo "<p style='color: orange; font-weight: bold;'>BŁĄD: Karta " . $karta . " już istnieje w systemie!</p>";
            echo "<p><a href='index.html'>Wróć do ekranu głównego</a></p>";
            exit();
        } else {
            $wstaw_sql = "INSERT INTO karty (numer_karty, pin) VALUES (:karta, :pin)";
            $insert_stmt = $pdo->prepare($wstaw_sql);
            $insert_stmt->execute(['karta' => $karta, 'pin' => $pin]);

            echo "<p style='color: green; font-weight: bold;'>REJESTRACJA UDANA!</p>";
            echo "<p>Nowa karta została dodana. <a href='index.html'>Zaloguj się teraz</a></p>";
            exit();
        }
    }

    // --- BLOK WYŚWIETLANIA PANELU DLA ZALOGOWANEGO UŻYTKOWNIKA ---
    // Ten kod wykona się tylko wtedy, gdy ktoś jest zalogowany w sesji
    if (isset($_SESSION['zalogowany_uzytkownik'])) {
        $karta_sesja = $_SESSION['zalogowany_uzytkownik'];
        
        // Pobieramy świeże dane o punktach z bazy
        $sql = "SELECT punkty FROM karty WHERE numer_karty = :karta";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['karta' => $karta_sesja]);
        $dane_klienta = $stmt->fetch();

        echo "<div style='background-color: #e9ecef; padding: 20px; border-radius: 5px; max-width: 400px;'>";
        echo "<h3>Konto aktywne</h3>";
        echo "<p>Numer karty: <strong>" . $karta_sesja . "</strong></p>";
        echo "<p>Twój stan konta to: <strong style='color: #007bff; font-size: 20px;'>" . $dane_klienta['punkty'] . " pkt</strong></p>";

        // Przycisk zakupu kawy
        echo "<form action='panel.php' method='post' style='display:inline;'>";
        echo "<button type='submit' name='akcja_kup_kawe' style='background-color: #ffc107; padding: 10px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;'>Kup kawę (50 pkt)</button>";
        echo "</form>";

        // Przycisk wylogowania
        echo "<form action='panel.php' method='post' style='display:inline; margin-left: 10px;'>";
        echo "<button type='submit' name='akcja_wyloguj' style='background-color: #dc3545; color: white; padding: 10px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;'>Wyloguj się</button>";
        echo "</form>";
        echo "</div>";
    }

} catch (PDOException $e) {
    echo "Błąd bazy danych: " . $e->getMessage();
}
?>
