SET NAMES utf8mb4;

CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  display_name VARCHAR(120) NOT NULL,
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_artist TINYINT(1) NOT NULL DEFAULT 0,
  paypal_email VARCHAR(254) NULL,
  paypal_email_updated_at DATETIME NULL,
  email_verified_at DATETIME NULL,
  last_login_at DATETIME NULL,
  last_login_ip VARBINARY(16) NULL,
  admin_2fa_secret VARCHAR(128) NULL,
  admin_2fa_enabled TINYINT(1) NOT NULL DEFAULT 0,
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
  purpose ENUM('login','register','password_reset','email_change') NOT NULL,
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

CREATE TABLE user_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  session_hash CHAR(64) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NULL,
  revoked_at DATETIME NULL,
  ip_address VARBINARY(16) NULL,
  user_agent VARCHAR(512) NULL,
  CONSTRAINT fk_user_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user_session_user (user_id,revoked_at,last_seen_at)
) ENGINE=InnoDB;

CREATE TABLE email_change_requests (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  new_email VARCHAR(190) NOT NULL,
  token_hash CHAR(64) NOT NULL UNIQUE,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_email_change_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_email_change_user (user_id,used_at,expires_at)
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
  owner_user_id BIGINT UNSIGNED NULL,
  bio TEXT NULL,
  image_path VARCHAR(255) NULL,
  website_url VARCHAR(255) NULL,
  instagram_url VARCHAR(255) NULL,
  soundcloud_url VARCHAR(255) NULL,
  youtube_url VARCHAR(255) NULL,
  banner_image_path VARCHAR(255) NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  catalogue_sort ENUM('release_desc','title_asc','sales_desc') NOT NULL DEFAULT 'release_desc',
  release_announcements_enabled TINYINT(1) NOT NULL DEFAULT 0,
  -- Deprecated compatibility mirror; payout source of truth is users.paypal_email.
  paypal_email VARCHAR(254) NULL,
  paypal_email_updated_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_artist_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_artist_owner (owner_user_id)
) ENGINE=InnoDB;

CREATE TABLE artist_followers (
  user_id BIGINT UNSIGNED NOT NULL,
  artist_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, artist_id),
  CONSTRAINT fk_artist_follower_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_artist_follower_artist FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
  INDEX idx_artist_followers_artist (artist_id, created_at)
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

CREATE TABLE artist_submissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  primary_artist_id BIGINT UNSIGNED NULL,
  artist_name VARCHAR(160) NOT NULL,
  title VARCHAR(190) NOT NULL,
  mix_name VARCHAR(120) NULL,
  genre_id BIGINT UNSIGNED NULL,
  bpm SMALLINT UNSIGNED NULL,
  master_path VARCHAR(255) NOT NULL,
  preview_path VARCHAR(255) NULL,
  artwork_path VARCHAR(255) NULL,
  file_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL DEFAULT 'audio/mpeg',
  file_size BIGINT UNSIGNED NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  review_note VARCHAR(500) NULL,
  reviewed_by BIGINT UNSIGNED NULL,
  reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_submission_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_submission_primary_artist FOREIGN KEY (primary_artist_id) REFERENCES artists(id) ON DELETE SET NULL,
  CONSTRAINT fk_submission_genre FOREIGN KEY (genre_id) REFERENCES genres(id) ON DELETE SET NULL,
  CONSTRAINT fk_submission_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_submission_status_created (status,created_at),
  INDEX idx_submission_user (user_id,created_at)
) ENGINE=InnoDB;

CREATE TABLE artist_submission_artists (
  submission_id BIGINT UNSIGNED NOT NULL,
  artist_id BIGINT UNSIGNED NOT NULL,
  role ENUM('primary','featured','remixer') NOT NULL DEFAULT 'featured',
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (submission_id, artist_id),
  CONSTRAINT fk_submission_artists_submission FOREIGN KEY (submission_id) REFERENCES artist_submissions(id) ON DELETE CASCADE,
  CONSTRAINT fk_submission_artists_artist FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
  INDEX idx_submission_artists_artist (artist_id, sort_order, submission_id)
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

CREATE TABLE track_artists (
  track_id BIGINT UNSIGNED NOT NULL,
  artist_id BIGINT UNSIGNED NOT NULL,
  role ENUM('primary','featured','remixer') NOT NULL DEFAULT 'featured',
  sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (track_id,artist_id),
  CONSTRAINT fk_track_artists_track FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
  CONSTRAINT fk_track_artists_artist FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE,
  INDEX idx_track_artists_artist (artist_id,sort_order,track_id)
) ENGINE=InnoDB;

CREATE TABLE preview_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  track_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  session_id VARCHAR(128) NULL,
  event_type ENUM('play','complete','skip','seek') NOT NULL,
  position_seconds DECIMAL(10,2) NULL,
  duration_seconds DECIMAL(10,2) NULL,
  section_seconds SMALLINT UNSIGNED NULL,
  ip_address VARBINARY(16) NULL,
  user_agent VARCHAR(512) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_preview_event_track FOREIGN KEY (track_id) REFERENCES tracks(id) ON DELETE CASCADE,
  CONSTRAINT fk_preview_event_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_preview_event_track_date (track_id, created_at),
  INDEX idx_preview_event_type_date (event_type, created_at),
  INDEX idx_preview_event_user_track (user_id, track_id, created_at)
) ENGINE=InnoDB;

INSERT INTO track_artists(track_id,artist_id,role,sort_order) SELECT id,artist_id,'primary',0 FROM tracks;

CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  payment_provider VARCHAR(40) NULL,
  payment_reference VARCHAR(190) NULL,
  gateway_capture_id VARCHAR(190) NULL,
  gateway_payload JSON NULL,
  gateway_refund_id VARCHAR(190) NULL,
  gateway_refund_payload JSON NULL,
  refunded_at DATETIME NULL,
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
  INDEX idx_order_gateway_capture (gateway_capture_id),
  INDEX idx_order_gateway_refund (gateway_refund_id)
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
('payout_host_percent','10.00'),('payout_paypal_confirmed','0'),('payout_automatic_enabled','0'),
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

CREATE TABLE artist_payouts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  artist_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  gross_pence BIGINT UNSIGNED NOT NULL DEFAULT 0,
  host_fee_pence BIGINT UNSIGNED NOT NULL DEFAULT 0,
  payout_pence BIGINT UNSIGNED NOT NULL DEFAULT 0,
  currency CHAR(3) NOT NULL DEFAULT 'GBP',
  status ENUM('processing','paid','failed','cancelled') NOT NULL DEFAULT 'processing',
  paypal_batch_id VARCHAR(190) NULL,
  paypal_item_id VARCHAR(190) NULL,
  paypal_response JSON NULL,
  error_message VARCHAR(500) NULL,
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  paid_at DATETIME NULL,
  CONSTRAINT fk_artist_payout_artist FOREIGN KEY (artist_id) REFERENCES artists(id),
  CONSTRAINT fk_artist_payout_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_artist_payout_admin FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_artist_payout_status (status,created_at),
  INDEX idx_artist_payout_artist_period (artist_id,period_start,period_end)
) ENGINE=InnoDB;

CREATE TABLE artist_payout_items (
  payout_id BIGINT UNSIGNED NOT NULL,
  order_item_id BIGINT UNSIGNED NOT NULL,
  artist_id BIGINT UNSIGNED NOT NULL,
  gross_pence INT UNSIGNED NOT NULL DEFAULT 0,
  host_fee_pence INT UNSIGNED NOT NULL DEFAULT 0,
  artist_share_pence INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (payout_id,order_item_id,artist_id),
  UNIQUE KEY uq_artist_payout_order_item (order_item_id,artist_id),
  CONSTRAINT fk_artist_payout_item_payout FOREIGN KEY (payout_id) REFERENCES artist_payouts(id) ON DELETE CASCADE,
  CONSTRAINT fk_artist_payout_item_order FOREIGN KEY (order_item_id) REFERENCES order_items(id),
  CONSTRAINT fk_artist_payout_item_artist FOREIGN KEY (artist_id) REFERENCES artists(id),
  INDEX idx_artist_payout_item_artist (artist_id,created_at)
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

CREATE TABLE support_tickets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  subject VARCHAR(190) NOT NULL,
  status ENUM('open','pending_customer','resolved','closed') NOT NULL DEFAULT 'open',
  priority ENUM('normal','high') NOT NULL DEFAULT 'normal',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_support_ticket_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_support_ticket_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
  INDEX idx_support_ticket_status (status,updated_at),
  INDEX idx_support_ticket_user (user_id,updated_at)
) ENGINE=InnoDB;

CREATE TABLE support_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NULL,
  admin_user_id BIGINT UNSIGNED NULL,
  message TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_support_message_ticket FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
  CONSTRAINT fk_support_message_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_support_message_admin FOREIGN KEY (admin_user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_support_message_ticket (ticket_id,created_at)
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
