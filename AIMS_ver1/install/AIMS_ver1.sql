CREATE DATABASE IF NOT EXISTS student_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE student_portal;

DROP TABLE IF EXISTS grades;
DROP TABLE IF EXISTS student_files;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
  id INT PRIMARY KEY,
  role VARCHAR(50) NOT NULL
);

INSERT INTO roles (id, role) VALUES
(1, 'Super Admin'),
(2, 'Registrar'),
(3, 'SAO'),
(4, 'Student');

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(190) UNIQUE,
  role_id INT NOT NULL,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

INSERT INTO users (username, password, email, role_id) VALUES
('admin', 'admin', 'admin@test.local', 1),
('registrar', 'registrar', 'registrar@test.local', 2),
('sao', 'sao', 'sao@test.local', 3),
('student1', 'student1', 'student1@test.local', 4);

CREATE TABLE grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  grade DECIMAL(5,2) NOT NULL,
  semester VARCHAR(50),
  school_year VARCHAR(20),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE student_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  posted_by INT,
  posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (posted_by) REFERENCES users(id)
);

INSERT INTO announcements (title, content, posted_by) VALUES
('Welcome to the Student Portal', 'This is a sample announcement from SAO.', 3);
