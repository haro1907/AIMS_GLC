CREATE DATABASE IF NOT EXISTS aims_ver1 CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE aims_ver1;

-- Drop existing tables in correct order
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS borrow_transactions;
DROP TABLE IF EXISTS inventory_items;
DROP TABLE IF EXISTS grades;
DROP TABLE IF EXISTS student_files;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

-- Create roles table
CREATE TABLE roles (
  id INT PRIMARY KEY,
  role VARCHAR(50) NOT NULL
);

INSERT INTO roles (id, role) VALUES
(1, 'Super Admin'),
(2, 'Registrar'),
(3, 'SAO'),
(4, 'Student');

-- Enhanced users table with password hashing and session management
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(100) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  email VARCHAR(190) UNIQUE,
  first_name VARCHAR(100),
  last_name VARCHAR(100),
  role_id INT NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  last_login TIMESTAMP NULL,
  session_token VARCHAR(255) NULL,
  session_expires TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- Insert sample users with hashed passwords (password123 for all)
INSERT INTO users (username, password, email, first_name, last_name, role_id) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@glc.edu', 'John', 'Administrator', 1),
('registrar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'registrar@glc.edu', 'Jane', 'Smith', 2),
('sao', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sao@glc.edu', 'Mike', 'Johnson', 3),
('student1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student1@glc.edu', 'Alice', 'Brown', 4),
('student2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student2@glc.edu', 'Bob', 'Wilson', 4);

-- Enhanced grades table
CREATE TABLE grades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  subject VARCHAR(100) NOT NULL,
  grade DECIMAL(5,2) NOT NULL,
  semester VARCHAR(50),
  school_year VARCHAR(20),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Enhanced student_files table
CREATE TABLE student_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  file_name VARCHAR(255) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  file_size INT,
  file_type VARCHAR(100),
  uploaded_by INT,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- Enhanced announcements table
CREATE TABLE announcements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  content TEXT NOT NULL,
  priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
  posted_by INT,
  is_active TINYINT(1) DEFAULT 1,
  posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (posted_by) REFERENCES users(id)
);

-- New inventory_items table for inventory management
CREATE TABLE inventory_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_code VARCHAR(50) UNIQUE NOT NULL,
  item_name VARCHAR(255) NOT NULL,
  description TEXT,
  category ENUM('Electronics', 'Furniture', 'Books', 'Sports Equipment', 'Laboratory Equipment', 'Office Supplies', 'Other') NOT NULL,
  quantity_total INT NOT NULL DEFAULT 0,
  quantity_available INT NOT NULL DEFAULT 0,
  quantity_borrowed INT NOT NULL DEFAULT 0,
  condition_status ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Damaged') DEFAULT 'Good',
  location VARCHAR(255),
  purchase_date DATE,
  purchase_price DECIMAL(10,2),
  is_borrowable TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- New borrow_transactions table
CREATE TABLE borrow_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_id INT NOT NULL,
  borrower_id INT NOT NULL,
  approved_by INT,
  quantity INT NOT NULL DEFAULT 1,
  borrow_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expected_return_date DATE NOT NULL,
  actual_return_date TIMESTAMP NULL,
  status ENUM('pending', 'approved', 'borrowed', 'returned', 'overdue', 'cancelled') DEFAULT 'pending',
  purpose TEXT,
  notes TEXT,
  return_condition ENUM('Excellent', 'Good', 'Fair', 'Poor', 'Damaged') NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (item_id) REFERENCES inventory_items(id) ON DELETE CASCADE,
  FOREIGN KEY (borrower_id) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- New activity_logs table for tracking user actions
CREATE TABLE activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(255) NOT NULL,
  table_name VARCHAR(100),
  record_id INT,
  old_values JSON,
  new_values JSON,
  ip_address VARCHAR(45),
  user_agent TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Insert sample inventory items
INSERT INTO inventory_items (item_code, item_name, description, category, quantity_total, quantity_available, location, condition_status, is_borrowable) VALUES
('PROJ001', 'Epson PowerLite Projector', 'High-resolution LCD projector for classrooms', 'Electronics', 5, 5, 'AV Equipment Room', 'Excellent', 1),
('LAPTOP001', 'Dell Latitude Laptop', 'Business laptop for faculty use', 'Electronics', 10, 8, 'IT Department', 'Good', 1),
('CHAIR001', 'Plastic Monobloc Chairs', 'White plastic chairs for events', 'Furniture', 100, 95, 'Storage Room A', 'Good', 1),
('MIC001', 'Wireless Microphone System', 'Professional wireless mic system', 'Electronics', 3, 3, 'Audio Equipment Room', 'Excellent', 1),
('BOOK001', 'Advanced Mathematics Textbook', 'Grade 12 Mathematics reference book', 'Books', 50, 45, 'Library', 'Good', 1),
('BALL001', 'Basketball Official Size', 'Official size basketball for PE classes', 'Sports Equipment', 20, 18, 'Sports Equipment Room', 'Good', 1);

-- Insert sample announcements
INSERT INTO announcements (title, content, posted_by, priority) VALUES
('Welcome to the Enhanced Student Portal', 'The system has been upgraded with new inventory management features and improved security.', 3, 'high'),
('New Borrowing System Available', 'Students and faculty can now borrow equipment and materials through the portal. Please check the inventory section.', 3, 'medium');

-- Insert sample grades
INSERT INTO grades (user_id, subject, grade, semester, school_year) VALUES
(4, 'Mathematics', 88.5, '1st Semester', '2024-2025'),
(4, 'Science', 92.0, '1st Semester', '2024-2025'),
(4, 'English', 85.5, '1st Semester', '2024-2025'),
(5, 'Mathematics', 90.0, '1st Semester', '2024-2025'),
(5, 'Science', 87.5, '1st Semester', '2024-2025');

-- Insert sample borrow transactions
INSERT INTO borrow_transactions (item_id, borrower_id, approved_by, quantity, expected_return_date, status, purpose) VALUES
(2, 4, 2, 1, DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'approved', 'Research project'),
(6, 5, 3, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY), 'borrowed', 'PE class activity');

-- Create indexes for better performance
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_users_session_token ON users(session_token);
CREATE INDEX idx_borrow_transactions_status ON borrow_transactions(status);
CREATE INDEX idx_borrow_transactions_return_date ON borrow_transactions(expected_return_date);
CREATE INDEX idx_inventory_items_category ON inventory_items(category);
CREATE INDEX idx_activity_logs_user_action ON activity_logs(user_id, action);

-- Create views for common queries
CREATE VIEW v_active_borrows AS
SELECT 
    bt.id,
    ii.item_name,
    ii.item_code,
    u.first_name,
    u.last_name,
    u.username,
    bt.quantity,
    bt.borrow_date,
    bt.expected_return_date,
    bt.status,
    bt.purpose,
    DATEDIFF(bt.expected_return_date, CURDATE()) as days_until_due
FROM borrow_transactions bt
JOIN inventory_items ii ON bt.item_id = ii.id
JOIN users u ON bt.borrower_id = u.id
WHERE bt.status IN ('approved', 'borrowed')
ORDER BY bt.expected_return_date ASC;

CREATE VIEW v_overdue_items AS
SELECT 
    bt.id,
    ii.item_name,
    ii.item_code,
    u.first_name,
    u.last_name,
    u.username,
    u.email,
    bt.quantity,
    bt.expected_return_date,
    DATEDIFF(CURDATE(), bt.expected_return_date) as days_overdue
FROM borrow_transactions bt
JOIN inventory_items ii ON bt.item_id = ii.id
JOIN users u ON bt.borrower_id = u.id
WHERE bt.status = 'borrowed' 
AND bt.expected_return_date < CURDATE()
ORDER BY days_overdue DESC;