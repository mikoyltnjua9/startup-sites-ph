<?php
// Shared helpers: labels, formatting, auth guard, CSRF.

declare(strict_types=1);

const STAGES = [
    'lead' => 'Lead',
    'proposal_sent' => 'Proposal Sent',
    'deposit_paid' => 'Deposit Paid',
    'in_design' => 'In Design',
    'in_development' => 'In Development',
    'in_review' => 'In Review',
    'launched' => 'Launched',
    'maintenance' => 'Maintenance',
];

const SERVICE_TYPES = [
    'web_design' => 'Web Design',
    'seo' => 'SEO',
    'smm' => 'Social Media Marketing',
    'ongoing_support' => 'Ongoing Support',
];

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function stage_label(string $stage): string
{
    return STAGES[$stage] ?? $stage;
}

function service_label(string $type): string
{
    return SERVICE_TYPES[$type] ?? $type;
}

function money(float $amount): string
{
    return '₱' . number_format($amount, 2);
}

function date_fmt(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('M j, Y', $ts) : '—';
}

/**
 * @return array{0: string, 1: string} [label, css class]
 */
function payment_status(float $total, float $paid): array
{
    if ($total <= 0) {
        return ['No amount set', 'muted'];
    }
    if ($paid >= $total) {
        return ['Paid in Full', 'paid'];
    }
    if ($paid > 0) {
        return ['Partially Paid', 'partial'];
    }
    return ['Unpaid', 'unpaid'];
}

function require_login(): void
{
    if (empty($_SESSION['is_admin'])) {
        header('Location: login.php');
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        die('Your session expired — go back and try again.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}
