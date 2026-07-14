<?php
// Expects $pageTitle to be set by the including page.
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($pageTitle ?? 'Dashboard') ?> · <?= h(APP_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= BASE_URL ?>public/css/style.css">
</head>
<body>
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark topbar sticky-top">
  <div class="container-fluid">
    <button class="btn btn-sm btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarOffcanvas">
      <i class="bi bi-list"></i>
    </button>
    <a class="navbar-brand fw-semibold" href="<?= BASE_URL ?>dashboard.php">
      <i class="bi bi-wallet2 me-1"></i> <?= h(APP_NAME) ?>
    </a>
    <div class="ms-auto d-flex align-items-center text-white">
      <span class="me-3 small">
        <i class="bi bi-person-circle me-1"></i><?= h($user['name']) ?>
        <span class="badge bg-light text-dark ms-1"><?= h(str_replace('_', ' ', ucwords($user['role'], '_'))) ?></span>
      </span>
      <a href="<?= BASE_URL ?>logout.php" class="btn btn-sm btn-outline-light"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </div>
</nav>
<div class="d-flex">
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="flex-grow-1 p-3 p-md-4 content-area">
<?php
  if ($msg = flash('success')) echo '<div class="alert alert-success alert-dismissible fade show">' . h($msg) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
  if ($msg = flash('error')) echo '<div class="alert alert-danger alert-dismissible fade show">' . h($msg) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
?>
<?php endif; ?>
