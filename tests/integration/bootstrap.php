<?php
declare(strict_types=1);

// Standalone integration harness: never loads .env or connects to the application database.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        require dirname(__DIR__, 2) . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    }
});

final class TestDatabase
{
    private PDO $server;
    private string $name;
    public PDO $pdo;

    public function __construct()
    {
        $dsn = 'mysql:host=' . (getenv('TEST_DB_HOST') ?: '127.0.0.1')
            . ';port=' . (getenv('TEST_DB_PORT') ?: '3306') . ';charset=utf8mb4';
        $user = getenv('TEST_DB_USER') ?: 'root';
        $password = getenv('TEST_DB_PASS') ?: '';
        $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
        $this->server = new PDO($dsn, $user, $password, $options);
        $this->name = 'bayer_test_' . bin2hex(random_bytes(8));
        $this->server->exec("CREATE DATABASE {$this->name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        try {
            $this->pdo = new PDO($dsn . ';dbname=' . $this->name, $user, $password, $options);
            $this->pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION,ONLY_FULL_GROUP_BY'");
        } catch (Throwable $error) {
            $this->close();
            throw $error;
        }
    }

    public function close(): void
    {
        if (isset($this->pdo) && $this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        if (preg_match('/^bayer_test_[a-f0-9]{16}$/D', $this->name)) {
            $this->server->exec("DROP DATABASE IF EXISTS {$this->name}");
        }
    }

    public function load(string $relativePath): void
    {
        $this->runSql(file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath));
    }

    public function runSql(string $script): void
    {
        $delimiter = ';';
        $buffer = '';
        foreach (preg_split('/\R/', $script) as $line) {
            if (preg_match('/^\s*DELIMITER\s+(\S+)\s*$/i', $line, $matches)) {
                $delimiter = $matches[1];
                continue;
            }
            if (preg_match('/^\s*--/', $line)) {
                continue;
            }
            $buffer .= $line . "\n";
            if (str_ends_with(rtrim($buffer), $delimiter)) {
                $statement = trim(substr(rtrim($buffer), 0, -strlen($delimiter)));
                if ($statement !== '') {
                    $this->pdo->exec($statement);
                }
                $buffer = '';
            }
        }
        ensure(trim($buffer) === '', 'SQL script ends with an unterminated statement');
    }
}

$GLOBALS['checks'] = 0;
function ensure(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    ++$GLOBALS['checks'];
}

function rejects(callable $operation, string $class = Throwable::class, ?int $driverCode = null): void
{
    try {
        $operation();
    } catch (Throwable $error) {
        ensure($error instanceof $class, "Expected $class, got " . get_class($error) . ': ' . $error->getMessage());
        if ($driverCode !== null) {
            ensure($error instanceof PDOException && (int) ($error->errorInfo[1] ?? 0) === $driverCode, 'Unexpected database error: ' . $error->getMessage());
        }
        return;
    }
    throw new RuntimeException('Expected operation to fail');
}

function fixtures(PDO $pdo): void
{
    $sql = [
        "INSERT INTO usuarios(id,nombre,email,password_hash) VALUES(1,'Test','fixture@example.invalid','not-a-login-hash'),(2,'Reviewer','reviewer@example.invalid','not-a-login-hash')",
        "INSERT INTO empresas(id,ruc,razon_social) VALUES(1,'00000000001','Fixture')",
        "INSERT INTO departamentos(id,nombre) VALUES(1,'D1'),(2,'D2')",
        "INSERT INTO provincias(id,departamento_id,nombre) VALUES(1,1,'P1'),(2,2,'P2')",
        "INSERT INTO distritos(id,provincia_id,nombre) VALUES(1,1,'X1'),(2,2,'X2')",
        "INSERT INTO sucursales(id,empresa_id,codigo,nombre,distrito_id) VALUES(1,1,'S1','Sucursal',1)",
        "INSERT INTO almacenes(id,sucursal_id,codigo,nombre) VALUES(1,1,'A1','Almacen'),(2,1,'A2','Almacen 2')",
        "INSERT INTO clientes(id,nro_doc,razon_social) VALUES(1,'00000001','Cliente')",
        "INSERT INTO vendedores(id,codigo,nombres) VALUES(1,'V1','Vendedor')",
        "INSERT INTO unidades_medida(id,codigo,nombre) VALUES(1,'KG','Kilogramo'),(2,'LT','Litro')",
        "INSERT INTO productos(id,codigo,nombre,unidad_base_id) VALUES(1,'M1','Producto 1',1),(2,'M2','Producto 2',2)",
        "INSERT INTO lotes(id,producto_id,codigo_lote,fecha_vencimiento) VALUES(1,1,'L1','2027-01-01'),(2,2,'L2','2027-02-01'),(3,1,'OLD','2020-01-01')",
        "INSERT INTO tipos_documento(id,codigo,nombre) VALUES(1,'01','Factura')",
        "INSERT INTO partners(id,codigo,nombre) VALUES(1,'BAYER','Bayer')",
    ];
    foreach ($sql as $statement) {
        $pdo->exec($statement);
    }
}

function docHeader(string $number = 'F001-1'): array
{
    return ['tipo_documento_id' => 1, 'numero' => $number, 'fecha' => '2026-09-11', 'cliente_id' => 1, 'vendedor_id' => 1, 'sucursal_id' => 1];
}

function guideHeader(string $number = 'T001-1'): array
{
    return ['numero' => $number, 'fecha' => '2026-09-11', 'cliente_id' => 1, 'vendedor_id' => 1, 'sucursal_id' => 1,
        'departamento_id' => 1, 'provincia_id' => 1, 'distrito_id' => 1];
}

function stockHeader(string $key = 'request-1', string $date = '2026-09-11'): array
{
    return ['fecha_stock' => $date, 'almacen_id' => 1, 'idempotency_key' => $key];
}

function lines(bool $stock = false): array
{
    $lines = [
        ['producto_id' => 1, 'unidad_id' => 1, 'cantidad' => '2.500'],
        ['producto_id' => 2, 'unidad_id' => 2, 'cantidad' => '1.000'],
    ];
    if ($stock) {
        $lines[0]['lote_id'] = 1;
        $lines[1]['lote_id'] = 2;
    }
    return $lines;
}
