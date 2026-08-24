<?php
session_start();
require_once 'dbconfig.php';

$error = '';

if (isset($_POST['submit'])) {

    $username = trim($_POST['uname'] ?? '');
    $password = $_POST['pass'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Kullanıcı adı ve şifre zorunludur.';

    } else {

        $stmt = $conn->prepare(
            "SELECT did, username, password, name
             FROM doctor
             WHERE username = ?
             LIMIT 1"
        );

        $stmt->bind_param('s', $username);
        $stmt->execute();

        $result = $stmt->get_result();
        $doctor = $result->fetch_assoc();

        if (
            $doctor &&
            hash_equals((string)$doctor['password'], (string)$password)
        ) {

            $_SESSION['doctor_id'] = (int)$doctor['did'];
            $_SESSION['doctor_username'] = $doctor['username'];
            $_SESSION['doctor_name'] = $doctor['name'];

            header('Location: ../doctor_dashboard.php');
            exit;

        } else {

            $error = 'Kullanıcı adı veya şifre hatalı.';
        }

        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="tr">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Doktor Girişi</title>

<link rel="stylesheet" href="main.css">

</head>

<body style="background-image:url(doctordesk.jpg)">

<form action="dlogin.php" method="post">

<div class="header">

<ul>
<li style="float:left;border-right:none">
<strong>Doctor Login</strong>
</li>
</ul>

</div>

<div class="sucontainer">

<?php if ($error !== ''): ?>

<div style="
    background:#f8d7da;
    color:#721c24;
    padding:12px;
    margin-bottom:15px;
    border-radius:6px;
">
<?php echo htmlspecialchars($error); ?>
</div>

<?php endif; ?>

<label>
<b>Username:</b>
</label>

<br>

<input
    type="text"
    placeholder="Enter Username"
    name="uname"
    required
>

<br>

<label>
<b>Password:</b>
</label>

<br>

<input
    type="password"
    placeholder="Enter Password"
    name="pass"
    required
>

<br><br>

<div class="container" style="background-color:grey">

<button
    type="button"
    onclick="history.back()"
    class="cancelbtn">
    Cancel
</button>

<button
    type="submit"
    name="submit"
    style="float:right">
    Log In
</button>

</div>

</div>

</form>

</body>
</html>