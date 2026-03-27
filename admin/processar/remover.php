<?php

$sqlite = "sqlite:../../login/db.db";
$pdo = new PDO($sqlite);session_start();
$id = $_GET['id'];



$sql = "DELETE FROM cc WHERE id=$id";
$pdo->exec($sql);

    header('Location: ../index.php');
