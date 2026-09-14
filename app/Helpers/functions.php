<?php
function base_path(string $path = ''): string { return dirname(__DIR__, 2) . ($path ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''); }
function load_env(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key,$value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) putenv("$key=$value");
        $_ENV[$key] = getenv($key);
    }
}
function env(string $key, mixed $default=null): mixed { $v = $_ENV[$key] ?? getenv($key); return ($v === false || $v === null || $v === '') ? $default : $v; }
function config(string $key, mixed $default=null): mixed {
    static $configs=[];
    [$file,$item] = array_pad(explode('.', $key, 2),2,null);
    if (!isset($configs[$file])) $configs[$file] = require base_path("config/$file.php");
    return $item ? ($configs[$file][$item] ?? $default) : $configs[$file];
}
function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    $c=config('database');
    $dsn="mysql:host={$c['host']};port={$c['port']};dbname={$c['name']};charset={$c['charset']}";
    $pdo=new PDO($dsn,$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    return $pdo;
}
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function url(string $path=''): string { $base=config('app.url',''); return $base . '/' . ltrim($path,'/'); }
function redirect(string $path): never { header('Location: '.url($path)); exit; }
function request_method(): string { return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'); }
function input(string $key, mixed $default=null): mixed { return $_POST[$key] ?? $_GET[$key] ?? $default; }
function flash(string $key, ?string $value=null): ?string { if ($value!==null){$_SESSION['_flash'][$key]=$value; return null;} $v=$_SESSION['_flash'][$key]??null; unset($_SESSION['_flash'][$key]); return $v; }
function csrf_token(): string { if(empty($_SESSION['_csrf'])) $_SESSION['_csrf']=bin2hex(random_bytes(32)); return $_SESSION['_csrf']; }
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="'.e(csrf_token()).'">'; }
function verify_csrf(): void
{
    if (request_method() !== 'POST') return;
    $sessionToken = $_SESSION['_csrf'] ?? null;
    $submittedToken = $_POST['_csrf'] ?? null;
    if (!is_string($sessionToken) || !is_string($submittedToken) || $sessionToken === '' || $submittedToken === '' || !hash_equals($sessionToken, $submittedToken)) {
        http_response_code(419);
        exit('CSRF token inválido.');
    }
}
function auth_user(): ?array { return $_SESSION['auth_user'] ?? null; }
function has_role(string ...$roles): bool { return App\Policies\AccessPolicy::allows(auth_user(), $roles); }
function require_auth(): void { if(!auth_user()) redirect('/login'); }
function require_role(string ...$roles): void { require_auth(); if(!has_role(...$roles)){(new App\Services\AuthService())->recordAccess('access_denied',(int)auth_user()['id']);throw new App\Exceptions\HttpException(403,'No tiene permisos para esta operación.');} }
function view(string $name, array $data=[]): void { extract($data); $view=base_path('app/Views/'.str_replace('.','/',$name).'.php'); require base_path('app/Views/layouts/header.php'); require $view; require base_path('app/Views/layouts/footer.php'); }
function log_event(string $message, array $context=[]): void { @file_put_contents(base_path('storage/logs/app.log'), '['.date('c').'] '.$message.' '.json_encode($context,JSON_UNESCAPED_UNICODE).PHP_EOL, FILE_APPEND | LOCK_EX); }
function audit(string $module,string $action,?int $entityId=null): void { $u=auth_user(); if(!$u)return; try{$st=db()->prepare('INSERT INTO auditoria_acciones(usuario_id,modulo,accion,entidad_id,fecha_hora) VALUES(?,?,?,?,NOW())');$st->execute([$u['id'],$module,$action,$entityId]);}catch(Throwable $e){log_event('audit_error',['e'=>$e->getMessage()]);} }

function is_local_env(): bool { return strtolower((string) config('app.env', 'production')) === 'local'; }

function branding(): array
{
    $defaults = [
        'system_name' => 'Pucchún',
        'system_subtitle' => 'Sistema de información',
        'partner_name' => 'Bayer',
        'partner_subtitle' => 'Portal de consulta',
        'internal_title' => 'Pucchún Data Hub',
        'portal_title' => 'Portal Bayer',
        'login_kicker' => 'PLATAFORMA DE INFORMACIÓN',
        'login_title' => 'Datos confiables. Decisiones claras.',
        'login_message' => 'Captura, validación, consulta y entrega en un solo entorno.',
        'footer_text' => 'Sistema de Gestión de Información',
        'primary_color' => '#075B9F',
        'accent_color' => '#168C5B',
        'sidebar_color' => '#0A2F55',
        'background_color' => '#F4F7FB',
        'sidebar_theme' => 'dark',
        'logo_primary' => null,
        'logo_partner' => null,
        'favicon' => null,
    ];

    $file = base_path('storage/config/branding.json');
    if (!is_file($file)) return $defaults;

    $raw = @file_get_contents($file);
    $data = is_string($raw) ? json_decode($raw, true) : null;
    if (!is_array($data)) return $defaults;

    $brand = array_merge($defaults, array_intersect_key($data, $defaults));

    foreach (['primary_color','accent_color','sidebar_color','background_color'] as $key) {
        if (!is_string($brand[$key]) || !preg_match('/^#[0-9A-Fa-f]{6}$/', $brand[$key])) {
            $brand[$key] = $defaults[$key];
        }
        $brand[$key] = strtoupper($brand[$key]);
    }

    $textLimits = [
        'system_name'=>80,
        'system_subtitle'=>100,
        'partner_name'=>80,
        'partner_subtitle'=>100,
        'internal_title'=>100,
        'portal_title'=>100,
        'login_kicker'=>80,
        'login_title'=>120,
        'login_message'=>220,
        'footer_text'=>140,
    ];
    foreach ($textLimits as $key=>$limit) {
        if (!is_string($brand[$key]) || trim($brand[$key]) === '' || mb_strlen($brand[$key]) > $limit) {
            $brand[$key] = $defaults[$key];
        } else {
            $brand[$key] = trim($brand[$key]);
        }
    }

    if (!in_array($brand['sidebar_theme'], ['dark','light'], true)) {
        $brand['sidebar_theme'] = $defaults['sidebar_theme'];
    }

    foreach (['logo_primary','logo_partner','favicon'] as $key) {
        if ($brand[$key] !== null && !is_string($brand[$key])) $brand[$key] = null;
    }

    return $brand;
}

function branding_logo_url(string $key): ?string
{
    $brand = branding();
    $relative = $brand[$key] ?? null;
    if (!is_string($relative) || $relative === '') return null;
    $absolute = base_path('public/' . ltrim($relative, '/'));
    return is_file($absolute) ? url('/' . ltrim($relative, '/')) : null;
}


function access_ip_label(?string $ip): string
{
    $ip = trim((string) $ip);
    if ($ip === '') return '—';
    if ($ip === '::1') return '127.0.0.1';
    if (str_starts_with(strtolower($ip), '::ffff:')) {
        $mapped = substr($ip, 7);
        if (filter_var($mapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return $mapped;
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '—';
}

function access_device_info(?string $userAgent): array
{
    $ua = trim((string) $userAgent);
    if ($ua === '') {
        return ['label' => 'No identificado', 'icon' => 'bi-question-circle', 'type' => 'unknown'];
    }

    $device = 'Equipo';
    $icon = 'bi-display';
    $type = 'desktop';

    if (preg_match('/iPad/i', $ua)) {
        $device = 'iPad';
        $icon = 'bi-tablet';
        $type = 'tablet';
    } elseif (preg_match('/iPhone|iPod/i', $ua)) {
        $device = 'iPhone';
        $icon = 'bi-phone';
        $type = 'mobile';
    } elseif (preg_match('/Android/i', $ua)) {
        if (preg_match('/Mobile/i', $ua)) {
            $device = 'Android';
            $icon = 'bi-phone';
            $type = 'mobile';
        } else {
            $device = 'Tablet Android';
            $icon = 'bi-tablet';
            $type = 'tablet';
        }
    } elseif (preg_match('/Windows NT/i', $ua)) {
        $device = 'Windows';
        $icon = 'bi-windows';
    } elseif (preg_match('/Macintosh|Mac OS X/i', $ua)) {
        $device = 'macOS';
        $icon = 'bi-apple';
    } elseif (preg_match('/Linux/i', $ua)) {
        $device = 'Linux';
        $icon = 'bi-display';
    }

    $browser = '';
    if (preg_match('/Edg\//i', $ua)) $browser = 'Edge';
    elseif (preg_match('/OPR\//i', $ua)) $browser = 'Opera';
    elseif (preg_match('/Chrome\//i', $ua)) $browser = 'Chrome';
    elseif (preg_match('/Firefox\//i', $ua)) $browser = 'Firefox';
    elseif (preg_match('/Safari\//i', $ua) && preg_match('/Version\//i', $ua)) $browser = 'Safari';

    return [
        'label' => $browser !== '' ? $device . ' · ' . $browser : $device,
        'icon' => $icon,
        'type' => $type,
    ];
}

function access_action_info(string $action): array
{
    return match (strtolower(trim($action))) {
        'login' => ['label' => 'Inicio de sesión', 'icon' => 'bi-box-arrow-in-right', 'class' => 'access-ok'],
        'logout' => ['label' => 'Sesión cerrada', 'icon' => 'bi-box-arrow-right', 'class' => 'access-neutral'],
        'login_failed' => ['label' => 'Inicio de sesión fallido', 'icon' => 'bi-shield-exclamation', 'class' => 'access-danger'],
        'login_blocked' => ['label' => 'Acceso bloqueado', 'icon' => 'bi-shield-lock', 'class' => 'access-danger'],
        'access_denied' => ['label' => 'Acceso denegado', 'icon' => 'bi-slash-circle', 'class' => 'access-danger'],
        default => ['label' => ucfirst(str_replace('_', ' ', trim($action))), 'icon' => 'bi-activity', 'class' => 'access-neutral'],
    };
}
