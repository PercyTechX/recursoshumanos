<?php

namespace App\Services\Backups;

use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Volcado (dump) SQL de la base de datos usando PDO puro — SIN el binario
 * `mysqldump`. Pensado para hosting compartido donde exec()/proc_open() suelen
 * estar bloqueados. Genera DROP + CREATE + INSERTs restaurables por phpMyAdmin.
 *
 * Solo MySQL/MariaDB (usa SHOW TABLES / SHOW CREATE TABLE). Ver docs/19.
 */
class DbDump
{
    /** Filas por sentencia INSERT (equilibra tamaño y memoria). */
    private const LOTE = 200;

    /** Devuelve el volcado SQL completo de la conexión por defecto. */
    public function generar(): string
    {
        $conn = DB::connection();

        if ($conn->getDriverName() !== 'mysql') {
            throw new RuntimeException('DbDump solo soporta MySQL/MariaDB (driver actual: '.$conn->getDriverName().').');
        }

        $pdo = $conn->getPdo();
        $base = $conn->getDatabaseName();

        $sql = "-- Backup de la base `{$base}`\n";
        $sql .= "-- Sistema RRHH — volcado PHP (sin mysqldump)\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
        $sql .= "SET NAMES utf8mb4;\n\n";

        foreach ($conn->select('SHOW TABLES') as $fila) {
            $tabla = array_values((array) $fila)[0];

            // Estructura
            $create = $conn->select("SHOW CREATE TABLE `{$tabla}`");
            $createSql = array_values((array) $create[0])[1] ?? '';
            $sql .= "DROP TABLE IF EXISTS `{$tabla}`;\n{$createSql};\n\n";

            // Datos (por cursor para no cargar toda la tabla en memoria)
            $columnas = null;
            $buffer = [];
            $volcar = function () use (&$buffer, &$columnas, &$sql, $tabla) {
                if (! $buffer) {
                    return;
                }
                $cols = '`'.implode('`,`', $columnas).'`';
                $sql .= "INSERT INTO `{$tabla}` ({$cols}) VALUES\n".implode(",\n", $buffer).";\n";
                $buffer = [];
            };

            foreach ($conn->table($tabla)->cursor() as $row) {
                $row = (array) $row;
                $columnas ??= array_keys($row);
                $vals = array_map(fn ($v) => $this->valor($v, $pdo), array_values($row));
                $buffer[] = '('.implode(',', $vals).')';
                if (count($buffer) >= self::LOTE) {
                    $volcar();
                }
            }
            $volcar();
            $sql .= "\n";
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        return $sql;
    }

    /** Formatea un valor como literal SQL seguro (escapa vía PDO::quote). */
    private function valor(mixed $v, PDO $pdo): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        return $pdo->quote((string) $v);
    }
}
