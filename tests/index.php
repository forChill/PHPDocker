<?php
// Lấy cấu hình từ file config.php
$config = require 'config.php';

// Truy cập thông tin cơ sở dữ liệu MySQL
$servername = $config['DB_HOST'];
$username = $config['DB_USER'];
$password = $config['DB_PASSWORD'];
$dbname = $config['DB_NAME'];

// Kết nối tới cơ sở dữ liệu MySQL
$conn = new mysqli($servername, $username, $password, $dbname);

// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Tạo bảng users nếu chưa tồn tại
$createTableSql = "
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL,
    email VARCHAR(50),
    reg_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($createTableSql);

// Kiểm tra dữ liệu trong bảng và thêm dữ liệu mẫu nếu cần
$checkDataSql = "SELECT COUNT(*) AS count FROM users";
$result = $conn->query($checkDataSql);
$row = $result->fetch_assoc();
if ($row['count'] == 0) {
    $insertDataSql = "INSERT INTO users (name, email) VALUES
        ('John Doe', 'john.doe@example.com'),
        ('Jane Smith', 'jane.smith@example.com')";
    $conn->query($insertDataSql);
}

// Lấy danh sách người dùng từ MySQL
$sql = "SELECT id, name, email, reg_date FROM users";
$result = $conn->query($sql);

// Kết nối Redis
require '../vendor/autoload.php'; // Nếu bạn dùng Predis

$redis = new Predis\Client([
    'scheme' => 'tcp',
    'host'   => 'redis',
    'port'   => 6379,
]);

// Kiểm tra kết nối Redis
try {
    $response = $redis->ping();
    if ($response->getPayload() === 'PONG') {
        echo "Connected to Redis successfully!<br>";
    }
} catch (Exception $e) {
    echo "Redis Error: " . $e->getMessage() . "<br>";
    exit;
}

// Thêm dữ liệu giả vào Redis
$redis->set('user:1', json_encode([
    'id' => 1,
    'name' => 'John Doe',
    'email' => 'john.doe@example.com'
]));
$redis->set('user:2', json_encode([
    'id' => 2,
    'name' => 'Jane Smith',
    'email' => 'jane.smith@example.com'
]));

// Lấy dữ liệu từ Redis
$cachedUser1 = $redis->get('user:1');
$cachedUser2 = $redis->get('user:2');

// Kết nối Elasticsearch
use Elastic\Elasticsearch\ClientBuilder;

$client = ClientBuilder::create()->setHosts(['http://elasticsearch:9200'])->build();

// Kiểm tra xem index 'users' có tồn tại không
if (!$client->indices()->exists(['index' => 'users'])->asBool()) {
    echo "Creating index 'users'...<br>";

    // Tạo index 'users' với mapping phù hợp
    $client->indices()->create([
        'index' => 'users',
        'body'  => [
            'mappings' => [
                'properties' => [
                    'id'    => ['type' => 'integer'],
                    'name'  => ['type' => 'text'],
                    'email' => ['type' => 'keyword'],
                ]
            ]
        ]
    ]);

    // Thêm dữ liệu mẫu vào index
    $client->index([
        'index' => 'users',
        'id'    => 1,
        'body'  => [
            'id'    => 1,
            'name'  => 'John Doe',
            'email' => 'john.doe@example.com',
        ]
    ]);

    $client->index([
        'index' => 'users',
        'id'    => 2,
        'body'  => [
            'id'    => 2,
            'name'  => 'Jane Smith',
            'email' => 'jane.smith@example.com',
        ]
    ]);

    // Làm mới chỉ mục để đảm bảo dữ liệu sẵn sàng
    $client->indices()->refresh(['index' => 'users']);
    echo "Index 'users' created and data added successfully!<br>";
} else {
    echo "Index 'users' already exists.<br>";
}

// Gửi truy vấn 'search' để lấy dữ liệu từ Elasticsearch
$params = [
    'index' => 'users',
    'body'  => [
        'query' => [
            'match_all' => (object)[]
        ]
    ]
];

try {
    $elasticsearchResponse = $client->search($params);
    // var_dump($elasticsearchResponse);die();
    if (!empty($elasticsearchResponse['hits']['hits'])) {
        foreach ($elasticsearchResponse['hits']['hits'] as $hit) {
            $source = $hit['_source'];
            echo "ID: {$source['id']}, Name: {$source['name']}, Email: {$source['email']}<br>";
        }
    } else {
        echo "No hits found in Elasticsearch.";
    }
} catch (\Elastic\Elasticsearch\Exception\ClientResponseException $e) {
    echo "Elasticsearch Error: " . $e->getMessage() . "<br>";
    exit;
}


// Tạo bảng HTML
echo '<!DOCTYPE html>
<html>
<head>
    <title>User List</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <h1>User List from MySQL</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Registration Date</th>
            </tr>
        </thead>
        <tbody>';

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo '<tr>
            <td>' . htmlspecialchars($row["id"]) . '</td>
            <td>' . htmlspecialchars($row["name"]) . '</td>
            <td>' . htmlspecialchars($row["email"]) . '</td>
            <td>' . htmlspecialchars($row["reg_date"]) . '</td>
        </tr>';
    }
} else {
    echo '<tr><td colspan="4">No users found in MySQL</td></tr>';
}

echo '      </tbody>
    </table>';

// Hiển thị dữ liệu từ Redis trong bảng HTML
echo '<h1>User List from Redis</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>';

if ($cachedUser1 && $cachedUser2) {
    $user1 = json_decode($cachedUser1, true);
    $user2 = json_decode($cachedUser2, true);

    echo '<tr>
        <td>' . htmlspecialchars($user1['id']) . '</td>
        <td>' . htmlspecialchars($user1['name']) . '</td>
        <td>' . htmlspecialchars($user1['email']) . '</td>
    </tr>';
    
    echo '<tr>
        <td>' . htmlspecialchars($user2['id']) . '</td>
        <td>' . htmlspecialchars($user2['name']) . '</td>
        <td>' . htmlspecialchars($user2['email']) . '</td>
    </tr>';
} else {
    echo '<tr><td colspan="3">No users found in Redis</td></tr>';
}

echo '      </tbody>
    </table>';

// Hiển thị dữ liệu từ Elasticsearch trong bảng HTML
echo '<h1>User List from Elasticsearch</h1>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
            </tr>
        </thead>
        <tbody>';

if (isset($elasticsearchResponse['hits']['hits'])) {
    foreach ($elasticsearchResponse['hits']['hits'] as $hit) {
        $source = $hit['_source'];
        echo '<tr>
            <td>' . htmlspecialchars($source['id']) . '</td>
            <td>' . htmlspecialchars($source['name']) . '</td>
            <td>' . htmlspecialchars($source['email']) . '</td>
        </tr>';
    }
} else {
    echo '<tr><td colspan="3">No users found in Elasticsearch</td></tr>';
}

echo '      </tbody>
    </table>
</body>
</html>';

// Đóng kết nối MySQL
$conn->close();
?>
