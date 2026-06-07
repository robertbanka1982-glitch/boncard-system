<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

echo "<h1>Centralny Panel Boncard v4.5</h1>";

$host = 'localhost';
$db   = 'boncard_db';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // --- 1. AKCJA: WYLOGOWANIE ---
    if (isset($_POST['akcja_wyloguj'])) {
        session_destroy();
        header("Location: index.html");
        exit();
    }

    // --- 2. AKCJA: KUPNO KAWY ---
    if (isset($_POST['akcja_kup_kawe'])) {
        $karta = $_SESSION['zalogowany_uzytkownik'];
        $koszt = 50;

        $sql = "SELECT punkty FROM karty WHERE numer_karty = :karta";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['karta' => $karta]);
        $klient = $stmt->fetch();

        if ($klient && $klient['punkty'] >= $koszt) {
            // Odejmujemy punkty
            $update_sql = "UPDATE karty SET punkty = punkty - :koszt WHERE numer_karty = :karta";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute(['koszt' => $koszt, 'karta' => $karta]);

            // Dodajemy wpis do historii
            $historia_sql = "INSERT INTO transakcje (numer_karty, opis, punkty_zmiana) VALUES (:karta, 'Zakup kawy', -50)";
            $historia_stmt = $pdo->prepare($historia_sql);
            $historia_stmt->execute(['karta' => $karta]);

            echo "<p style='color: green; font-weight: bold;'>TRANSAKCJA UDANA! Punkty zostały pobrane.</p>";
        } else {
            echo "<p style='color: red; font-weight: bold;'>BŁĄD: Brak środków na karcie!</p>";
        }
    }
    
    // --- 3. AKCJA: LOGOWANIE Z INDEX.HTML ---
    elseif (isset($_POST['akcja_zaloguj'])) {
        $karta = $_POST['numer_karty'] ?? '';
        $pin   = $_POST['pin'] ?? '';

        $sql = "SELECT * FROM karty WHERE numer_karty = :karta AND pin = :pin";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['karta' => $karta, 'pin' => $pin]);
        $klient = $stmt->fetch();

        if ($klient) {
            $_SESSION['zalogowany_uzytkownik'] = $klient['numer_karty'];
        } else {
            echo "<p style='color: red; font-weight: bold;'>BŁĄD: Zły numer karty lub PIN!</p>";
            echo "<p><a href='index.html'>Wróć i spróbuj ponownie</a></p>";
            exit();
        }
    } 
    
    // --- 4. AKCJA: REJESTRACJA Z INDEX.HTML ---
    elseif (isset($_POST['akcja_zarejestruj'])) {
        $karta = $_POST['numer_karty'] ?? '';
        $pin   = $_POST['pin'] ?? '';

        $sprawdz_sql = "SELECT * FROM karty WHERE numer_karty = :karta";
        $stmt = $pdo->prepare($sprawdz_sql);
        $stmt->execute(['karta' => $karta]);
        
        if ($stmt->fetch()) {
            echo "<p style='color: orange; font-weight: bold;'>BŁĄD: Karta już istnieje!</p>";
            echo "<p><a href='index.html'>Wróć do ekranu głównego</a></p>";
            exit();
        } else {
            $wstaw_sql = "INSERT INTO karty (numer_karty, pin) VALUES (:karta, :pin)";
            $insert_stmt = $pdo->prepare($wstaw_sql);
            $insert_stmt->execute(['karta' => $karta, 'pin' => $pin]);

            $historia_reg_sql = "INSERT INTO transakcje (numer_karty, opis, punkty_zmiana) VALUES (:karta, 'Aktywacja karty', 100)";
            $historia_reg_stmt = $pdo->prepare($historia_reg_sql);
            $historia_reg_stmt->execute(['karta' => $karta]);

            echo "<p style='color: green; font-weight: bold;'>REJESTRACJA UDANA!</p>";
            echo "<p><a href='index.html'>Zaloguj się teraz</a></p>";
            exit();
        }
    }

    // --- 5. WYŚWIETLANIE PANELU DLA ZALOGOWANEGO ---
    if (isset($_SESSION['zalogowany_uzytkownik'])) {
        $karta_sesja = $_SESSION['zalogowany_uzytkownik'];
        
        $sql = "SELECT punkty FROM karty WHERE numer_karty = :karta";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['karta' => $karta_sesja]);
        $dane_klienta = $stmt->fetch();

        // Panel informacyjny (prosty, czysty HTML)
        echo "<div style='border: 2px solid #ccc; padding: 15px; margin-bottom: 20px; max-width: 400px;'>";
        echo "<h3>Konto aktywne</h3>";
        echo "<p>Numer karty: <strong>" . $karta_sesja . "</strong></p>";
        echo "<p>Twój stan konta to: <strong style='color: blue;'>" . $dane_klienta['punkty'] . " pkt</strong></p>";

        // Przycisk "Kup kawę" w osobnym formularzu
        echo "<form action='panel.php' method='POST' style='margin-bottom: 10px;'>";
        echo "<input type='submit' name='akcja_kup_kawe' value='Kup kawę (50 pkt)' style='padding: 8px; font-weight: bold; cursor: pointer;'>";
        echo "</form>";

        // Przycisk "Wyloguj" w osobnym formularzu
        echo "<form action='panel.php' method='POST'>";
        echo "<input type='submit' name='akcja_wyloguj' value='Wyloguj się' style='padding: 8px; cursor: pointer;'>";
        echo "</form>";
        echo "</div>";

        // Tabela historii pod spodem
        echo "<h3>Historia operacji:</h3>";
        $pobierz_historie_sql = "SELECT * FROM transakcje WHERE numer_karty = :karta ORDER BY data_transakcji DESC";
        $historia_stmt = $pdo->prepare($pobierz_historie_sql);
        $historia_stmt->execute(['karta' => $karta_sesja]);
        $wszystkie_transakcje = $historia_stmt->fetchAll();

        if ($wszystkie_transakcje) {
            echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%; max-width: 500px;'>";
            echo "<tr style='background-color: #eee;'><th>Data</th><th>Operacja</th><th>Punkty</th></tr>";
            foreach ($wszystkie_transakcje as $t) {
                $kolor = ($t['punkty_zmiana'] < 0) ? 'red' : 'green';
                echo "<tr>";
                echo "<td>" . $t['data_transakcji'] . "</td>";
                echo "<td>" . $t['opis'] . "</td>";
                echo "<td style='color: $kolor; font-weight: bold;'>" . $t['punkty_zmiana'] . " pkt</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Brak operacji na tej karcie.</p>";
        }
    }

} catch (PDOException $e) {
    echo "<h2 style='color: red;'>Błąd bazy danych:</h2><p>" . $e->getMessage() . "</p>";
}
?>
