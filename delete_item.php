<?php
require 'db.php';
$id = $_GET['id'];
$conn->query("DELETE FROM items WHERE id=$id");
header("Location: items.php");
exit();
