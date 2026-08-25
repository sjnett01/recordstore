<?php
declare(strict_types=1);
require_once __DIR__.'/admin_layout.php';

function admin_users_layout_start(string $current): void { admin_layout_start('Users', $current); }
function admin_users_layout_end(): void { admin_layout_end(); }
function admin_users_redirect(string $path, string $message = ''): never { if ($message !== '') $_SESSION['admin_flash'] = ['message' => $message, 'error' => false]; redirect($path); }
function admin_users_flash(): void { if (!empty($_SESSION['admin_flash'])) { $flash = $_SESSION['admin_flash']; unset($_SESSION['admin_flash']); echo '<div class="panel admin-flash '.(!empty($flash['error']) ? 'error' : '').'">'.e($flash['message']).'</div>'; } }
