-- Run this on your PlanetScale / MySQL database
CREATE TABLE users (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tg_id VARCHAR(32) UNIQUE NOT NULL,
  username VARCHAR(255),
  first_name VARCHAR(255),
  balance DECIMAL(10,2) DEFAULT 0.00,
  referral_code VARCHAR(50),
  referred_by VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tasks (
  id INT PRIMARY KEY AUTO_INCREMENT,
  title VARCHAR(255),
  reward DECIMAL(6,2) DEFAULT 0.00,
  type VARCHAR(50), -- e.g., 'watch', 'follow', 'install'
  url TEXT,
  active TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE completions (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  task_id INT,
  proof TEXT,
  reward DECIMAL(6,2),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (task_id) REFERENCES tasks(id)
);

CREATE TABLE withdrawals (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  amount DECIMAL(10,2),
  method VARCHAR(50),
  account_info TEXT,
  status ENUM('pending','approved','paid','rejected') DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE referrals (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  user_id BIGINT,
  referred_user_id BIGINT,
  reward DECIMAL(6,2),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
