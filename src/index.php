<?php
// Lấy cấu hình từ file config.php
$config = require 'config.php';
// Truy cập thông tin cơ sở dữ liệu
$servername = $config['DB_HOST'];
$username = $config['DB_USER'];
$password = $config['DB_PASSWORD'];
$dbname = $config['DB_NAME'];

// Tạo kết nối
$conn = new mysqli($servername, $username, $password, $dbname);



// Kiểm tra kết nối
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Thực hiện truy vấn
$sql = "SELECT id, name, email FROM users";
$result = $conn->query($sql);

// Kiểm tra nếu có kết quả
if ($result->num_rows > 0) {
    // Xuất dữ liệu từ mỗi hàng
    while($row = $result->fetch_assoc()) {
        echo "id: " . $row["id"]. " - Name: " . $row["name"]. " - Email: " . $row["email"]. "<br>";
    }
} else {
    echo "0 results<br>";

    // Nếu không có kết quả, tạo bảng và thêm dữ liệu vào bảng
    $createTableSql = "CREATE TABLE IF NOT EXISTS users (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(30) NOT NULL,
        email VARCHAR(50),
        reg_date TIMESTAMP
    )";

    if ($conn->query($createTableSql) === TRUE) {
        echo "Table users created successfully<br>";

        // Thêm dữ liệu vào bảng
        $insertDataSql = "INSERT INTO users (name, email) VALUES
        ('John Doe', 'john.doe@example.com'),
        ('Jane Smith', 'jane.smith@example.com')";

        if ($conn->query($insertDataSql) === TRUE) {
            echo "New records created successfully<br>";
        } else {
            echo "Error: " . $insertDataSql . "<br>" . $conn->error;
        }
    } else {
        echo "Error creating table: " . $conn->error . "<br>";
    }
}

// Đóng kết nối
$conn->close();
?>
