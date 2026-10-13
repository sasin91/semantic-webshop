<?php
/**
 * Run a .sql file from db/ statement by statement. Shared by the installer
 * (schema) and the vector store (its table depends on the MariaDB version).
 */
trait Sql_files {

    /**
     * Statements end with ; at the end of a line; -- comment lines are skipped
     *
     * @param array<string, string> $replacements text to replace, e.g. ['VECTOR(768)' => 'VECTOR(256)']
     */
    function _run_sql_file(string $path, array $replacements = []): void {
        $sql = strtr(file_get_contents($path), $replacements);
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        foreach (preg_split('/;\s*$/m', $sql) as $statement) {
            if (trim($statement) !== '') {
                $this->db->query($statement);
            }
        }
    }
}
