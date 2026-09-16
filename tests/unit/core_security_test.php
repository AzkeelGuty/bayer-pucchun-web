<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/Helpers/functions.php';
spl_autoload_register(static function ($class) {
    $file = dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});
use App\Policies\AccessPolicy;
use App\Services\SessionService;
use App\Validators\LoginValidator;

$checks = 0;
function check(bool $result, string $message): void {
    if (!$result) throw new RuntimeException($message);
    ++$GLOBALS['checks'];
}
foreach (['ADMIN','DIGITADOR','SUPERVISOR','GERENCIA','BAYER'] as $role) {
    $user = ['roles'=>[$role]];
    check(AccessPolicy::allows($user, AccessPolicy::INTERNAL) === ($role !== 'BAYER'), 'Internal access ' . $role);
    check(AccessPolicy::allows($user, AccessPolicy::CAPTURE) === in_array($role,['ADMIN','DIGITADOR'],true), 'Capture access ' . $role);
    check(AccessPolicy::allows($user, AccessPolicy::REVIEW) === in_array($role,['ADMIN','SUPERVISOR'],true), 'Review access ' . $role);
}
check(!AccessPolicy::allows(['roles'=>['ADMIN','BAYER']], AccessPolicy::INTERNAL), 'Mixed Bayer role denied');
check(AccessPolicy::landing(['roles'=>['ADMIN','BAYER']]) === '/bayer', 'Mixed role external landing');
check(!AccessPolicy::allows(null, AccessPolicy::INTERNAL), 'Guest denied');
check(!AccessPolicy::allows(['roles'=>['UNKNOWN']], AccessPolicy::INTERNAL), 'Unknown role denied');
check(AccessPolicy::allows(['role'=>'ADMIN'], AccessPolicy::INTERNAL), 'Legacy session format');
$v = new LoginValidator();
check($v->valid('user@example.invalid','test'), 'Valid login shape');
foreach ([[[], 'test'],['user@example.invalid',[]],['invalid','test'],['user@example.invalid',''],['user@example.invalid',str_repeat('x',4097)]] as [$email,$password]) {
    check(!$v->valid($email,$password), 'Invalid login shape');
}
$_SESSION = [];
check(!SessionService::expired(10000), 'Guest is not expired');
$_SESSION = ['auth_user'=>['id'=>1]];
check(SessionService::expired(10000), 'Old session without activity expires');
$_SESSION['_last_activity'] = 10000;
check(!SessionService::expired(10001), 'Recent session retained');
check(SessionService::expired(11800), 'Exact timeout boundary');
$_SESSION['_last_activity'] = 10001;
check(SessionService::expired(10000), 'Future timestamp rejected');
echo "Core security: $checks checks OK\n";
