<?php
require_once __DIR__ . '/../config/auth.php';
unset($_SESSION['superadmin_id'],$_SESSION['supercsrf'],$_SESSION['super_flash'],$_SESSION['credencial_gestor']);
session_regenerate_id(true);
header('Location: login.php'); exit;
