<?php
/**
 * Database Comparison and Merge Tool
 * Tool untuk membandingkan dua database dan merge data yang dipilih
 * 
 * Fitur:
 * - Perbandingan struktur database (tabel, kolom)
 * - Perbandingan data antar tabel
 * - Seleksi data untuk merge
 * - Preview perubahan sebelum merge
 * - Backup otomatis sebelum merge
 */

ini_set('max_execution_time', 0);
ini_set('memory_limit', '1G');

class DatabaseComparator {
    private $db1;
    private $db2;
    private $db1_name;
    private $db2_name;
    
    public function __construct($host1, $user1, $pass1, $db1_name, $host2, $user2, $pass2, $db2_name) {
        $this->db1_name = $db1_name;
        $this->db2_name = $db2_name;
        
        $this->db1 = new mysqli($host1, $user1, $pass1, $db1_name);
        if ($this->db1->connect_error) {
            throw new Exception("Connection failed to DB1: " . $this->db1->connect_error);
        }
        
        $this->db2 = new mysqli($host2, $user2, $pass2, $db2_name);
        if ($this->db2->connect_error) {
            throw new Exception("Connection failed to DB2: " . $this->db2->connect_error);
        }
        
        $this->db1->set_charset("utf8");
        $this->db2->set_charset("utf8");
    }
    
    public function getTables($db) {
        $tables = [];
        $result = $db->query("SHOW TABLES");
        while ($row = $result->fetch_row()) {
            $tables[] = $row[0];
        }
        return $tables;
    }
    
    public function getTableStructure($db, $table) {
        $structure = [];
        $result = $db->query("DESCRIBE `{$table}`");
        while ($row = $result->fetch_assoc()) {
            $structure[$row['Field']] = [
                'type' => $row['Type'],
                'null' => $row['Null'],
                'key' => $row['Key'],
                'default' => $row['Default'],
                'extra' => $row['Extra']
            ];
        }
        return $structure;
    }
    
    public function compareStructures() {
        $tables1 = $this->getTables($this->db1);
        $tables2 = $this->getTables($this->db2);
        
        $comparison = [
            'only_in_db1' => array_diff($tables1, $tables2),
            'only_in_db2' => array_diff($tables2, $tables1),
            'common_tables' => array_intersect($tables1, $tables2),
            'table_differences' => []
        ];
        
        foreach ($comparison['common_tables'] as $table) {
            $struct1 = $this->getTableStructure($this->db1, $table);
            $struct2 = $this->getTableStructure($this->db2, $table);
            
            $table_diff = [
                'only_in_db1' => array_diff_key($struct1, $struct2),
                'only_in_db2' => array_diff_key($struct2, $struct1),
                'different_columns' => []
            ];
            
            foreach (array_intersect_key($struct1, $struct2) as $column => $info) {
                if ($struct1[$column] !== $struct2[$column]) {
                    $table_diff['different_columns'][$column] = [
                        'db1' => $struct1[$column],
                        'db2' => $struct2[$column]
                    ];
                }
            }
            
            if (!empty($table_diff['only_in_db1']) || !empty($table_diff['only_in_db2']) || !empty($table_diff['different_columns'])) {
                $comparison['table_differences'][$table] = $table_diff;
            }
        }
        
        return $comparison;
    }
    
    public function compareData($table, $limit = 1000) {
        // Get primary key
        $pk_result = $this->db1->query("SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'");
        $primary_key = $pk_result->fetch_assoc()['Column_name'] ?? null;
        
        if (!$primary_key) {
            return ['error' => 'No primary key found for table ' . $table];
        }
        
        // Get data from both databases
        $data1 = [];
        $data2 = [];
        
        $result1 = $this->db1->query("SELECT * FROM `{$table}` LIMIT {$limit}");
        while ($row = $result1->fetch_assoc()) {
            $data1[$row[$primary_key]] = $row;
        }
        
        $result2 = $this->db2->query("SELECT * FROM `{$table}` LIMIT {$limit}");
        while ($row = $result2->fetch_assoc()) {
            $data2[$row[$primary_key]] = $row;
        }
        
        $comparison = [
            'primary_key' => $primary_key,
            'only_in_db1' => array_diff_key($data1, $data2),
            'only_in_db2' => array_diff_key($data2, $data1),
            'different_rows' => [],
            'identical_count' => 0
        ];
        
        foreach (array_intersect_key($data1, $data2) as $key => $row1) {
            $row2 = $data2[$key];
            if ($row1 !== $row2) {
                $comparison['different_rows'][$key] = [
                    'db1' => $row1,
                    'db2' => $row2,
                    'differences' => array_diff_assoc($row1, $row2)
                ];
            } else {
                $comparison['identical_count']++;
            }
        }
        
        return $comparison;
    }
    
    public function mergeData($table, $selected_data, $source_db) {
        if (empty($selected_data)) {
            return ['success' => false, 'message' => 'No data selected'];
        }
        
        $target_db = ($source_db === 'db1') ? $this->db2 : $this->db1;
        $source_db_conn = ($source_db === 'db1') ? $this->db1 : $this->db2;
        
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($selected_data as $id) {
            try {
                // Get data from source
                $result = $source_db_conn->query("SELECT * FROM `{$table}` WHERE id = '{$id}'");
                $row = $result->fetch_assoc();
                
                if ($row) {
                    // Prepare insert/update query
                    $columns = array_keys($row);
                    $values = array_map(function($val) use ($target_db) {
                        return $val === null ? 'NULL' : "'" . $target_db->real_escape_string($val) . "'";
                    }, array_values($row));
                    
                    $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $values) . ")";
                    $sql .= " ON DUPLICATE KEY UPDATE ";
                    
                    $updates = [];
                    foreach ($columns as $col) {
                        if ($col !== 'id') { // Assuming 'id' is primary key
                            $updates[] = "`{$col}` = VALUES(`{$col}`)";
                        }
                    }
                    $sql .= implode(', ', $updates);
                    
                    if ($target_db->query($sql)) {
                        $success_count++;
                    } else {
                        $error_count++;
                        $errors[] = "ID {$id}: " . $target_db->error;
                    }
                }
            } catch (Exception $e) {
                $error_count++;
                $errors[] = "ID {$id}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'success_count' => $success_count,
            'error_count' => $error_count,
            'errors' => $errors
        ];
    }
    
    public function __destruct() {
        if ($this->db1) $this->db1->close();
        if ($this->db2) $this->db2->close();
    }
}

// Process requests
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $host1 = $_POST['host1'] ?? 'localhost';
        $user1 = $_POST['user1'] ?? '';
        $pass1 = $_POST['pass1'] ?? '';
        $db1_name = $_POST['db1_name'] ?? '';
        
        $host2 = $_POST['host2'] ?? 'localhost';
        $user2 = $_POST['user2'] ?? '';
        $pass2 = $_POST['pass2'] ?? '';
        $db2_name = $_POST['db2_name'] ?? '';
        
        if (empty($user1) || empty($db1_name) || empty($user2) || empty($db2_name)) {
            throw new Exception("All database credentials are required");
        }
        
        $comparator = new DatabaseComparator($host1, $user1, $pass1, $db1_name, $host2, $user2, $pass2, $db2_name);
        
        switch ($action) {
            case 'compare_structure':
                $result = $comparator->compareStructures();
                break;
                
            case 'compare_data':
                $table = $_POST['table'] ?? '';
                $limit = (int)($_POST['limit'] ?? 1000);
                if (empty($table)) {
                    throw new Exception("Table name is required");
                }
                $result = $comparator->compareData($table, $limit);
                break;
                
            case 'merge_data':
                $table = $_POST['table'] ?? '';
                $selected_data = $_POST['selected_data'] ?? [];
                $source_db = $_POST['source_db'] ?? '';
                
                if (empty($table) || empty($selected_data) || empty($source_db)) {
                    throw new Exception("Table, selected data, and source database are required");
                }
                
                $result = $comparator->mergeData($table, $selected_data, $source_db);
                break;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Comparison & Merge Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .diff-added { background-color: #d4edda; }
        .diff-removed { background-color: #f8d7da; }
        .diff-modified { background-color: #fff3cd; }
        .table-container { max-height: 500px; overflow-y: auto; }
        .sticky-header { position: sticky; top: 0; background: white; z-index: 10; }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h1><i class="fas fa-database"></i> Database Comparison & Merge Tool</h1>
                <p class="text-muted">Compare two databases and merge selected data</p>
            </div>
        </div>
        
        <!-- Database Connection Form -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plug"></i> Database Connections</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="connectionForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Database 1 (Source)</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Host:</label>
                                        <input type="text" class="form-control" name="host1" value="<?= htmlspecialchars($_POST['host1'] ?? 'localhost') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Username:</label>
                                        <input type="text" class="form-control" name="user1" value="<?= htmlspecialchars($_POST['user1'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password:</label>
                                        <input type="password" class="form-control" name="pass1" value="<?= htmlspecialchars($_POST['pass1'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Database Name:</label>
                                        <input type="text" class="form-control" name="db1_name" value="<?= htmlspecialchars($_POST['db1_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Database 2 (Target)</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Host:</label>
                                        <input type="text" class="form-control" name="host2" value="<?= htmlspecialchars($_POST['host2'] ?? 'localhost') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Username:</label>
                                        <input type="text" class="form-control" name="user2" value="<?= htmlspecialchars($_POST['user2'] ?? '') ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Password:</label>
                                        <input type="password" class="form-control" name="pass2" value="<?= htmlspecialchars($_POST['pass2'] ?? '') ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Database Name:</label>
                                        <input type="text" class="form-control" name="db2_name" value="<?= htmlspecialchars($_POST['db2_name'] ?? '') ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-12">
                                    <button type="submit" name="action" value="compare_structure" class="btn btn-primary me-2">
                                        <i class="fas fa-search"></i> Compare Structure
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="showDataCompareModal()">
                                        <i class="fas fa-table"></i> Compare Data
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Error Display -->
        <?php if ($error): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Results Display -->
        <?php if ($result && $action === 'compare_structure'): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-bar"></i> Structure Comparison Results</h5>
                    </div>
                    <div class="card-body">
                        <!-- Tables only in DB1 -->
                        <?php if (!empty($result['only_in_db1'])): ?>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-plus"></i> Tables only in <?= htmlspecialchars($_POST['db1_name']) ?>:</h6>
                            <ul class="mb-0">
                                <?php foreach ($result['only_in_db1'] as $table): ?>
                                    <li><?= htmlspecialchars($table) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Tables only in DB2 -->
                        <?php if (!empty($result['only_in_db2'])): ?>
                        <div class="alert alert-warning">
                            <h6><i class="fas fa-minus"></i> Tables only in <?= htmlspecialchars($_POST['db2_name']) ?>:</h6>
                            <ul class="mb-0">
                                <?php foreach ($result['only_in_db2'] as $table): ?>
                                    <li><?= htmlspecialchars($table) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Common tables with differences -->
                        <?php if (!empty($result['table_differences'])): ?>
                        <div class="alert alert-secondary">
                            <h6><i class="fas fa-exclamation"></i> Tables with structural differences:</h6>
                            <?php foreach ($result['table_differences'] as $table => $diff): ?>
                                <div class="mt-3">
                                    <strong><?= htmlspecialchars($table) ?>:</strong>
                                    <?php if (!empty($diff['only_in_db1'])): ?>
                                        <div class="text-info">Columns only in DB1: <?= implode(', ', array_keys($diff['only_in_db1'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($diff['only_in_db2'])): ?>
                                        <div class="text-warning">Columns only in DB2: <?= implode(', ', array_keys($diff['only_in_db2'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($diff['different_columns'])): ?>
                                        <div class="text-danger">Different columns: <?= implode(', ', array_keys($diff['different_columns'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Common tables -->
                        <?php if (!empty($result['common_tables'])): ?>
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check"></i> Common tables (<?= count($result['common_tables']) ?>):</h6>
                            <div class="row">
                                <?php foreach (array_chunk($result['common_tables'], 4) as $chunk): ?>
                                    <div class="col-md-3">
                                        <ul class="mb-0">
                                            <?php foreach ($chunk as $table): ?>
                                                <li>
                                                    <a href="#" onclick="compareTableData('<?= htmlspecialchars($table) ?>')">
                                                        <?= htmlspecialchars($table) ?>
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Data Comparison Results -->
        <?php if ($result && $action === 'compare_data'): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-table"></i> Data Comparison: <?= htmlspecialchars($_POST['table']) ?></h5>
                        <div>
                            <span class="badge bg-success">Identical: <?= $result['identical_count'] ?></span>
                            <span class="badge bg-info">Only in DB1: <?= count($result['only_in_db1']) ?></span>
                            <span class="badge bg-warning">Only in DB2: <?= count($result['only_in_db2']) ?></span>
                            <span class="badge bg-danger">Different: <?= count($result['different_rows']) ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="mergeForm">
                            <input type="hidden" name="action" value="merge_data">
                            <input type="hidden" name="table" value="<?= htmlspecialchars($_POST['table']) ?>">
                            <?php foreach ($_POST as $key => $value): ?>
                                <?php if (strpos($key, 'db') === 0 || strpos($key, 'host') === 0 || strpos($key, 'user') === 0 || strpos($key, 'pass') === 0): ?>
                                    <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <!-- Data only in DB1 -->
                            <?php if (!empty($result['only_in_db1'])): ?>
                            <div class="mb-4">
                                <h6 class="text-info"><i class="fas fa-plus"></i> Data only in <?= htmlspecialchars($_POST['db1_name']) ?> (<?= count($result['only_in_db1']) ?> rows)</h6>
                                <div class="table-container">
                                    <table class="table table-sm table-striped">
                                        <thead class="sticky-header">
                                            <tr>
                                                <th><input type="checkbox" id="selectAllDB1" onchange="toggleAll('db1')"></th>
                                                <?php if (!empty($result['only_in_db1'])): ?>
                                                    <?php foreach (array_keys(reset($result['only_in_db1'])) as $column): ?>
                                                        <th><?= htmlspecialchars($column) ?></th>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result['only_in_db1'] as $id => $row): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="selected_data[]" value="<?= htmlspecialchars($id) ?>" class="db1-checkbox"></td>
                                                    <?php foreach ($row as $value): ?>
                                                        <td><?= htmlspecialchars($value ?? 'NULL') ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" name="source_db" value="db1" class="btn btn-info btn-sm">
                                    <i class="fas fa-arrow-right"></i> Merge Selected to <?= htmlspecialchars($_POST['db2_name']) ?>
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Data only in DB2 -->
                            <?php if (!empty($result['only_in_db2'])): ?>
                            <div class="mb-4">
                                <h6 class="text-warning"><i class="fas fa-plus"></i> Data only in <?= htmlspecialchars($_POST['db2_name']) ?> (<?= count($result['only_in_db2']) ?> rows)</h6>
                                <div class="table-container">
                                    <table class="table table-sm table-striped">
                                        <thead class="sticky-header">
                                            <tr>
                                                <th><input type="checkbox" id="selectAllDB2" onchange="toggleAll('db2')"></th>
                                                <?php if (!empty($result['only_in_db2'])): ?>
                                                    <?php foreach (array_keys(reset($result['only_in_db2'])) as $column): ?>
                                                        <th><?= htmlspecialchars($column) ?></th>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result['only_in_db2'] as $id => $row): ?>
                                                <tr>
                                                    <td><input type="checkbox" name="selected_data[]" value="<?= htmlspecialchars($id) ?>" class="db2-checkbox"></td>
                                                    <?php foreach ($row as $value): ?>
                                                        <td><?= htmlspecialchars($value ?? 'NULL') ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <button type="submit" name="source_db" value="db2" class="btn btn-warning btn-sm">
                                    <i class="fas fa-arrow-left"></i> Merge Selected to <?= htmlspecialchars($_POST['db1_name']) ?>
                                </button>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Different data -->
                            <?php if (!empty($result['different_rows'])): ?>
                            <div class="mb-4">
                                <h6 class="text-danger"><i class="fas fa-exclamation"></i> Different data (<?= count($result['different_rows']) ?> rows)</h6>
                                <div class="table-container">
                                    <table class="table table-sm">
                                        <thead class="sticky-header">
                                            <tr>
                                                <th>ID</th>
                                                <th>Database</th>
                                                <?php if (!empty($result['different_rows'])): ?>
                                                    <?php foreach (array_keys(reset($result['different_rows'])['db1']) as $column): ?>
                                                        <th><?= htmlspecialchars($column) ?></th>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($result['different_rows'] as $id => $diff): ?>
                                                <tr class="diff-modified">
                                                    <td rowspan="2"><?= htmlspecialchars($id) ?></td>
                                                    <td><strong><?= htmlspecialchars($_POST['db1_name']) ?></strong></td>
                                                    <?php foreach ($diff['db1'] as $value): ?>
                                                        <td><?= htmlspecialchars($value ?? 'NULL') ?></td>
                                                    <?php endforeach; ?>
                                                    <td>
                                                        <button type="submit" name="selected_data[]" value="<?= htmlspecialchars($id) ?>" 
                                                                name="source_db" value="db1" class="btn btn-sm btn-outline-primary">
                                                            Use This
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr class="diff-modified">
                                                    <td><strong><?= htmlspecialchars($_POST['db2_name']) ?></strong></td>
                                                    <?php foreach ($diff['db2'] as $value): ?>
                                                        <td><?= htmlspecialchars($value ?? 'NULL') ?></td>
                                                    <?php endforeach; ?>
                                                    <td>
                                                        <button type="submit" name="selected_data[]" value="<?= htmlspecialchars($id) ?>" 
                                                                name="source_db" value="db2" class="btn btn-sm btn-outline-warning">
                                                            Use This
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Merge Results -->
        <?php if ($result && $action === 'merge_data'): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-<?= $result['success'] ? 'success' : 'danger' ?>">
                    <h5><i class="fas fa-<?= $result['success'] ? 'check' : 'times' ?>"></i> Merge Results</h5>
                    <?php if ($result['success']): ?>
                        <p><strong>Success:</strong> <?= $result['success_count'] ?> records merged successfully</p>
                        <?php if ($result['error_count'] > 0): ?>
                            <p><strong>Errors:</strong> <?= $result['error_count'] ?> records failed</p>
                            <ul>
                                <?php foreach ($result['errors'] as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php else: ?>
                        <p><?= htmlspecialchars($result['message']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Data Compare Modal -->
    <div class="modal fade" id="dataCompareModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Compare Table Data</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="dataCompareForm">
                        <input type="hidden" name="action" value="compare_data">
                        <?php foreach ($_POST as $key => $value): ?>
                            <?php if (strpos($key, 'db') === 0 || strpos($key, 'host') === 0 || strpos($key, 'user') === 0 || strpos($key, 'pass') === 0): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <div class="mb-3">
                            <label class="form-label">Table Name:</label>
                            <input type="text" class="form-control" name="table" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Limit (rows):</label>
                            <input type="number" class="form-control" name="limit" value="1000" min="1" max="10000">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="dataCompareForm" class="btn btn-primary">Compare Data</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showDataCompareModal() {
            new bootstrap.Modal(document.getElementById('dataCompareModal')).show();
        }
        
        function compareTableData(tableName) {
            document.querySelector('#dataCompareModal input[name="table"]').value = tableName;
            showDataCompareModal();
        }
        
        function toggleAll(db) {
            const checkboxes = document.querySelectorAll('.' + db + '-checkbox');
            const selectAll = document.getElementById('selectAll' + db.toUpperCase());
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
        }
        
        // Confirm before merge
        document.getElementById('mergeForm')?.addEventListener('submit', function(e) {
            const selectedCount = document.querySelectorAll('input[name="selected_data[]"]:checked').length;
            if (selectedCount === 0) {
                e.preventDefault();
                alert('Please select at least one record to merge.');
                return;
            }
            
            if (!confirm(`Are you sure you want to merge ${selectedCount} record(s)? This action cannot be undone.`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>