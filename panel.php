<?php
echo "<h1>Centralny Panel Boncard v3.5</h1>";

$host = 'localhost';
$db   = 'boncard_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);

    // --- SCENARIUSZ 1: UŻYTKOWNIK KLIKNĄŁ "KUP KAWĘ" ---
    if (isset($_POST['akcja_kup_kawe'])) {
        $karta = $_POST['numer_karty'];
        $koszt = 50;

        // Pobieramy aktualny stan konta klienta
        $sql = "SELECT punkty FROM karty WHERE numer_karty = :karta";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['karta' => $karta]);
        $klient = $stmt->fetch();

        if ($klient && $klient['punkty'] >= $koszt) {
            // Jeśli ma punkty, odejmujemy je w bazie danych (UPDATE)
            $update_sql = "UPDATE karty SET punkty = punkty - :koszt WHERE numer_karty = :karta";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute(['koszt' => $koszt, 'karta' => $karta]);

            echo "<p style='color: green; font-weight: bold;'>TRANSAKCJA UDANA! Zakupiono kawę za 50 punktów.</p>";
            echo "<p><a href='index.html'>Wróć do ekranu głównego, aby sprawdzić nowy stan konta.</a></p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>BŁĄD: Brak wystarczających środków na karcie!</p>";
            echo "<p><a href='index.html'>Wróć do ekranu głównego.</a></p>";
        }
    }
    
    // --- SCENARIUSZ 2: UŻYTKOWNIK KLIKNĄŁ "ZALOGUJ SIĘ" ---
    elseif (isset($_POST['akcja_zaloguj'])) {
        $karta = $_POST['numer_karty'] ?? '';
        $pin   = $_POST['pin'] ?? '';

        $sql = "SELECT * FROM karty WHERE numer_karty = :karta AND pin = :pin";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['karta' => $karta, 'pin' => $pin]);
        $klient = $stmt->fetch();

        if ($klient) {
            echo "<p style='color: green; font-weight: bold;'>ZALOGOWANO POMYŚLNIE!</p>";
            echo "<p>Witaj z powrotem!</p>";
            echo "<p>Twój obecny stan konta to: <strong style='font-size: 20px; color: #007bff;'>" . $klient['punkty'] . " punktów</strong>.</p>";

            // NOWOŚĆ: Ukryty formularz pozwalający na zakup kawy
            echo "<form action='panel.php' method='post'>";
            echo "<input type='hidden' name='numer_karty' value='" . $klient['numer_karty'] . "'>";
            echo "<button type='submit' name='akcja_kup_kawe' style='background-color: #ffc107; color: black; padding: 10px 15px; border: none; border-radius: 4px; font-weight: bold; cursor: pointer;'>Kup kawę za 50 punktów</button>";
            echo "</form>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>BŁĄD: Niepoprawny numer karty lub PIN!</p>";
        }
    } 
    
    // --- SCENARIUSZ 3: UŻYTKOWNIK KLIKNĄŁ "ZAREJESTRUJ" ---
    elseif (isset($_POST['akcja_zarejestruj'])) {
        $karta = $_POST['numer_karty'] ?? '';
        $pin   = $_POST['pin'] ?? '';

        $sprawdz_sql = "SELECT * FROM karty WHERE numer_karty = :karta";
        $stmt = $pdo->prepare($sprawdz_sql);
        $stmt->execute(['karta' => $karta]);
        
        if ($stmt->fetch()) {
            echo "<p style='color: orange; font-weight: bold;'>BŁĄD: Karta " . $karta . " już istnieje w systemie!</p>";
        } else {
            $wstaw_sql = "INSERT INTO karty (numer_karty, pin) VALUES (:karta, :pin)";
            $insert_stmt = $pdo->prepare($wstaw_sql);
            $insert_stmt->execute(['karta' => $karta, 'pin' => $pin]);

            echo "<p style='color: green; font-weight: bold;'>REJESTRACJA UDANA!</p>";
            echo "<p>Nowa karta o numerze " . $karta . " została dodana. Domyślnie otrzymała 100 punktów startowych.</p>";
        }
    }

} catch (PDOException $e) {
    echo "Błąd bazy danych: " . $e->getMessage();
}
?>