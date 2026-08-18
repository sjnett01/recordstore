SET NAMES utf8mb4;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  disabled_at DATETIME NULL,
  banned_at DATETIME NULL,
  ban_reason VARCHAR(255) NULL,
  last_login_at DATETIME NULL,
  disabled_at DATETIME NULL,
  banned_at DATETIME NULL,
  ban_reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;



CREATE TABLE audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(80) NOT NULL,
  ip_address VARBINARY(16) NULL,
  user_agent VARCHAR(512) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_created (created_at),
  INDEX idx_audit_event (event_type,created_at),
  INDEX idx_audit_user (user_id,created_at)
) ENGINE=InnoDB;
CREATE TABLE auth_otp_challenges (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  purpose ENUM('login','register') NOT NULL,
  display_name VARCHAR(120) NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_otp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_otp_email_purpose (email,purpose,created_at),
  INDEX idx_otp_expiry (expires_at)
) ENGINE=InnoDB;


CREATE TABLE mail_templates (
  template_key VARCHAR(80) NOT NULL PRIMARY KEY,
  label VARCHAR(160) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 1,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  html_body MEDIUMTEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT INTO mail_templates(template_key,label,enabled,subject,body) VALUES
('order_paid','Successful order',1,'Order {{order_id}} confirmed','Hello {{customer_name}},\n\nThank you for your order with {{site_name}}.\n\nOrder: {{order_id}}\nTotal: {{total}}\nPayment status: Paid\n\nYour downloads are available from your account.\n\n{{site_name}}'),
('order_status','Payment/order status update',1,'Order {{order_id}} status update','Hello {{customer_name}},\n\nYour order {{order_id}} status is now: {{status}}.\n\nTotal: {{total}}\n\n{{site_name}}'),
('order_cancelled','Cancelled order',1,'Order {{order_id}} cancelled','Hello {{customer_name}},\n\nYour order {{order_id}} has been cancelled. No further download access is available for this order.\n\n{{site_name}}')
ON DUPLICATE KEY UPDATE label=VALUES(label);
CREATE TABLE artists (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  bio TEXT NULL,
  image_path VARCHAR(255) NULL,
  website_url VARCHAR(255) NULL,
  instagram_url VARCHAR(255) NULL,
  soundcloud_url VARCHAR(255) NULL,
  youtube_url VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE labels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(160) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE genres (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE releases (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  artist_id BIGINT UNSIGNED NOT NULL,
  label_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(210) NOT NULL UNIQUE,
  description TEXT NULL,
  artwork_path VARCHAR(255) NULL,
  release_date DATE NOT NULL,
  catalogue_number VARCHAR(80) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_release_artist FOREIGN KEY (artist_id) REFERENCES artists(id),
  CONSTRAINT fk_release_label FOREIGN KEY (label_id) REFERENCES labels(id)
) ENGINE=InnoDB;

CREATE TABLE tracks (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  release_id BIGINT UNSIGNED NULL,
  artist_id BIGINT UNSIGNED NOT NULL,
  genre_id BIGINT UNSIGNED NULL,
  title VARCHAR(190) NOT NULL,
  mix_name VARCHAR(120) NULL,
  bpm SMALLINT UNSIGNED NULL,
  price_pence INT UNSIGNED NOT NULL DEFAULT 149,
  master_path VARCHAR(255) NOT NULL,
  preview_path VARCHAR(255) NULL,
  artwork_path VARCHAR(255) NULL,
  file_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL DEFAULT 'audio/mpeg',
  file_size BIGINT UNSIGNED NULL,
  release_date DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_track_release FOREIGN KEY (release_id) REFERENCES releases(id),
  CONSTRAINT fk_track_artist FOREIGN KEY (artist_id) REFERENCES artists(id),
  CONSTRAINT fk_track_genre FOREIGN KEY (genre_id) REFERENCES genres(id),
  INDEX idx_track_active (active),
  INDEX idx_track_artist (artist_id),
  INDEX idx_track_genre (genre_id),
  INDEX idx_track_release_date (release_date)
) ENGINE=InnoDB;

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  payment_provider VARCHAR(40) NULL,
  payment_reference VARCHAR(190) NULL,
  gateway_capture_id VARCHAR(190) NULL,
  gateway_payload JSON NULL,
  subtotal_pence INT UNSIGNED NOT NULL,
  total_pence INT UNSIGNED NOT NULL,
  discount_code VARCHAR(64) NULL,
  discount_pence INT UNSIGNED NOT NULL DEFAULT 0,
  vat_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
  vat_pence BIGINT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  CONSTRAINT fk_order_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_order_user_status (user_id,status),
  INDEX idx_order_payment_reference (payment_reference),
  INDEX idx_order_gateway_capture (gateway_capture_id)
) ENGINE=InnoDB;


CREATE TABLE store_settings (
  setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
  setting_value TEXT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE payment_webhook_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider VARCHAR(40) NOT NULL,
  event_id VARCHAR(190) NOT NULL,
  event_type VARCHAR(120) NULL,
  verification_status VARCHAR(40) NULL,
  payload_json LONGTEXT NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_payment_webhook_event (provider,event_id)
) ENGINE=InnoDB;

INSERT INTO store_settings(setting_key,setting_value) VALUES
('payment_gateway','paypal'),('paypal_mode','sandbox'),
('mail_transport','local'),('mail_from_name','RecordStore Digital'),('mail_from_email',''),
('smtp_host',''),('smtp_port','587'),('smtp_security','tls'),('smtp_username',''),('smtp_password','');

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  track_id BIGINT UNSIGNED NOT NULL,
  unit_price_pence INT UNSIGNED NOT NULL,
  download_limit SMALLINT UNSIGNED NOT NULL DEFAULT 3,
  downloads_used SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_track FOREIGN KEY (track_id) REFERENCES tracks(id),
  UNIQUE KEY uq_order_track(order_id,track_id)
) ENGINE=InnoDB;

CREATE TABLE order_status_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id BIGINT UNSIGNED NOT NULL,
  old_status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL,
  new_status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  note VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_status_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_order_status_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_order_status_order_created (order_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE download_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_item_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  ip_address VARBINARY(16) NULL,
  user_agent VARCHAR(255) NULL,
  downloaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dl_item FOREIGN KEY (order_item_id) REFERENCES order_items(id),
  CONSTRAINT fk_dl_user FOREIGN KEY (user_id) REFERENCES users(id),
  INDEX idx_dl_item (order_item_id)
) ENGINE=InnoDB;

CREATE TABLE sales_daily (
  sales_date DATE NOT NULL,
  track_id BIGINT UNSIGNED NOT NULL,
  units INT UNSIGNED NOT NULL DEFAULT 0,
  revenue_pence BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (sales_date, track_id),
  CONSTRAINT fk_sales_track FOREIGN KEY (track_id) REFERENCES tracks(id)
) ENGINE=InnoDB;

INSERT IGNORE INTO genres(name,slug) VALUES
('Bounce','bounce'),('Donk','donk'),('Hard Dance','hard-dance'),('Hardcore','hardcore'),('Trance','trance'),('UK Hardcore','uk-hardcore');

CREATE TABLE user_favourites (
  user_id BIGINT UNSIGNED NOT NULL,
  track_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, track_id),
  CONSTRAINT fk_favourite_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_favourite_track FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
  INDEX idx_favourite_track (track_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE discount_codes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(64) NOT NULL UNIQUE,
  promotion_kind ENUM('code','launch','genre','bundle') NOT NULL DEFAULT 'code',
  user_id BIGINT UNSIGNED NULL,
  genre_id BIGINT UNSIGNED NULL,
  one_time_per_user TINYINT(1) NOT NULL DEFAULT 0,
  discount_type ENUM('percent','fixed') NOT NULL,
  discount_value INT UNSIGNED NOT NULL,
  minimum_subtotal_pence INT UNSIGNED NOT NULL DEFAULT 0,
  minimum_items INT UNSIGNED NOT NULL DEFAULT 0,
  auto_apply TINYINT(1) NOT NULL DEFAULT 0,
  starts_at DATETIME NULL,
  ends_at DATETIME NULL,
  usage_limit INT UNSIGNED NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_discount_active_dates (active,starts_at,ends_at),
  INDEX idx_discount_promotion (promotion_kind,auto_apply,genre_id),
  CONSTRAINT fk_discount_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_discount_genre FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE newsletter_subscribers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NULL,
  email VARCHAR(254) NOT NULL UNIQUE,
  confirmation_hash CHAR(64) NULL,
  confirmed_at DATETIME NULL,
  unsubscribed_at DATETIME NULL,
  source VARCHAR(40) NOT NULL DEFAULT 'site',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_newsletter_status (confirmed_at,unsubscribed_at),
  CONSTRAINT fk_newsletter_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE mail_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  recipient_email VARCHAR(254) NOT NULL,
  template_key VARCHAR(80) NULL,
  subject VARCHAR(255) NOT NULL,
  status ENUM('sent','failed') NOT NULL,
  error_message VARCHAR(500) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_mail_log_created (created_at),
  INDEX idx_mail_log_status (status)
) ENGINE=InnoDB;

CREATE TABLE cart_snapshots (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(128) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  cart_json JSON NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  reminder_sent_at DATETIME NULL,
  UNIQUE KEY uq_cart_snapshot_session (session_id),
  INDEX idx_cart_snapshot_reminder (updated_at,reminder_sent_at),
  CONSTRAINT fk_cart_snapshot_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE installation_settings (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  installed TINYINT(1) NOT NULL DEFAULT 0,
  installation_id CHAR(32) NOT NULL,
  package_name VARCHAR(120) NOT NULL,
  package_version VARCHAR(40) NOT NULL,
  app_name VARCHAR(120) NOT NULL,
  base_url VARCHAR(255) NOT NULL,
  timezone VARCHAR(80) NOT NULL,
  mail_transport VARCHAR(20) NOT NULL,
  scheduler_method VARCHAR(30) NOT NULL,
  admin_email VARCHAR(254) NOT NULL,
  schema_version VARCHAR(40) NULL,
  metadata_json JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
