SET NAMES utf8mb4;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  is_blocked    TINYINT(1) NOT NULL DEFAULT 0,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE code_batches (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_name VARCHAR(120) NOT NULL,
  points_value INT UNSIGNED NOT NULL,
  quantity     INT UNSIGNED NOT NULL,
  created_by   INT UNSIGNED NOT NULL,
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_batch_points CHECK (points_value > 0),
  CONSTRAINT chk_batch_qty    CHECK (quantity > 0),
  FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE product_codes (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_id   INT UNSIGNED NOT NULL,
  code_hash  CHAR(64) NOT NULL UNIQUE,        -- SHA-256 hex; plaintext never stored
  status     ENUM('unused','claimed','void') NOT NULL DEFAULT 'unused',
  claimed_by INT UNSIGNED NULL,
  claimed_at DATETIME NULL,
  claimed_ip VARCHAR(45) NULL,
  FOREIGN KEY (batch_id)   REFERENCES code_batches(id),
  FOREIGN KEY (claimed_by) REFERENCES users(id),
  INDEX idx_status (status)
) ENGINE=InnoDB;

CREATE TABLE rewards (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  description TEXT NULL,
  points_cost INT UNSIGNED NOT NULL,
  stock       INT UNSIGNED NOT NULL DEFAULT 0,
  is_active   TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT chk_reward_cost CHECK (points_cost > 0)
) ENGINE=InnoDB;

CREATE TABLE redemptions (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  reward_id    INT UNSIGNED NOT NULL,
  points_spent INT UNSIGNED NOT NULL,
  status       ENUM('pending','fulfilled','cancelled') NOT NULL DEFAULT 'pending',
  created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id)   REFERENCES users(id),
  FOREIGN KEY (reward_id) REFERENCES rewards(id)
) ENGINE=InnoDB;

-- Ledger: balance = SUM(points). Earn rows are positive, redeem rows negative.
CREATE TABLE point_transactions (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id       INT UNSIGNED NOT NULL,
  type          ENUM('earn','redeem') NOT NULL,
  points        INT NOT NULL,
  code_id       INT UNSIGNED NULL UNIQUE,      -- one credit per code, ever
  redemption_id INT UNSIGNED NULL UNIQUE,      -- one debit per redemption
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT chk_sign CHECK (
    (type = 'earn' AND points > 0) OR (type = 'redeem' AND points < 0)
  ),
  FOREIGN KEY (user_id)       REFERENCES users(id),
  FOREIGN KEY (code_id)       REFERENCES product_codes(id),
  FOREIGN KEY (redemption_id) REFERENCES redemptions(id),
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- Used for rate limiting both logins and bad code claims
CREATE TABLE login_attempts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  attempt_type ENUM('login','claim') NOT NULL,
  ip           VARCHAR(45) NOT NULL,
  identifier   VARCHAR(190) NULL,              -- email or user id
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_lookup (attempt_type, ip, attempted_at)
) ENGINE=InnoDB;
