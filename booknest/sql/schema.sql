-- BookNest database schema (MariaDB 10.4 / MySQL 8)
-- Import this first, then seed.sql. Re-importing drops and recreates everything.

DROP DATABASE IF EXISTS booknest;
CREATE DATABASE booknest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE booknest;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name     VARCHAR(80)  NOT NULL,
  email         VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('member','admin') NOT NULL DEFAULT 'member',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;

CREATE TABLE categories (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(60) NOT NULL,
  slug        VARCHAR(60) NOT NULL,
  blurb       VARCHAR(160) NOT NULL DEFAULT '',
  sort_order  TINYINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB;

CREATE TABLE books (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  serial_no      CHAR(10)     NOT NULL,
  isbn           CHAR(13)     NULL,
  title          VARCHAR(150) NOT NULL,
  author         VARCHAR(100) NOT NULL,
  publisher      VARCHAR(80)  NULL,
  format         VARCHAR(20)  NOT NULL DEFAULT 'Paperback',
  category_id    INT UNSIGNED NOT NULL,
  hook           VARCHAR(200) NOT NULL DEFAULT '',
  synopsis       TEXT         NOT NULL,
  sample_text    LONGTEXT     NULL,
  sample_type    ENUM('excerpt','preview') NOT NULL DEFAULT 'excerpt',
  price          DECIMAL(8,2) NOT NULL,
  rating         DECIMAL(2,1) NOT NULL DEFAULT 0.0,
  published_year SMALLINT     NOT NULL,
  pages          SMALLINT UNSIGNED NOT NULL,
  stock          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  cover_path     VARCHAR(120) NOT NULL,
  cover_alt      VARCHAR(200) NOT NULL,
  is_featured    TINYINT(1)   NOT NULL DEFAULT 0,
  is_staff_pick  TINYINT(1)   NOT NULL DEFAULT 0,
  status         ENUM('approved','pending','rejected') NOT NULL DEFAULT 'pending',
  added_by       INT UNSIGNED NULL,
  created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_books_serial (serial_no),
  KEY ix_books_status_cat (status, category_id),
  KEY ix_books_title (title),
  KEY ix_books_author (author),
  KEY ix_books_isbn (isbn),
  KEY ix_books_created (created_at),
  CONSTRAINT fk_books_category FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT fk_books_user FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE orders (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NULL,
  email           VARCHAR(120) NOT NULL,
  full_name       VARCHAR(80)  NOT NULL,
  phone           VARCHAR(20)  NOT NULL,
  delivery_method ENUM('delivery','pickup') NOT NULL,
  address         VARCHAR(200) NULL,
  note            VARCHAR(300) NULL,
  subtotal        DECIMAL(10,2) NOT NULL,
  delivery_fee    DECIMAL(6,2)  NOT NULL DEFAULT 0,
  total           DECIMAL(10,2) NOT NULL,
  payment_status  ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_orders_user (user_id),
  KEY ix_orders_status_date (payment_status, created_at),
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id    INT UNSIGNED NOT NULL,
  book_id     INT UNSIGNED NOT NULL,
  qty         SMALLINT UNSIGNED NOT NULL,
  unit_price  DECIMAL(8,2) NOT NULL,
  KEY ix_items_order (order_id),
  KEY ix_items_book (book_id),
  CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  CONSTRAINT fk_items_book FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB;

CREATE TABLE study_rooms (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(40) NOT NULL,
  capacity   TINYINT UNSIGNED NOT NULL,
  floor      VARCHAR(20) NOT NULL,
  features   VARCHAR(120) NOT NULL,
  image_path VARCHAR(120) NOT NULL,
  is_active  TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY uq_rooms_name (name)
) ENGINE=InnoDB;

CREATE TABLE room_bookings (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  room_id      INT UNSIGNED NOT NULL,
  user_id      INT UNSIGNED NOT NULL,
  booking_date DATE NOT NULL,
  start_time   TIME NOT NULL,
  end_time     TIME NOT NULL,
  purpose      VARCHAR(120) NULL,
  status       ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_bookings_room_date (room_id, booking_date),
  KEY ix_bookings_user_date (user_id, booking_date),
  CONSTRAINT fk_bookings_room FOREIGN KEY (room_id) REFERENCES study_rooms(id),
  CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE shelf (
  user_id  INT UNSIGNED NOT NULL,
  book_id  INT UNSIGNED NOT NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, book_id),
  CONSTRAINT fk_shelf_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_shelf_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Questions put to Paige, the help assistant, so staff can see what people ask and what she could not answer.
CREATE TABLE assistant_log (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NULL,
  question   VARCHAR(300) NOT NULL,
  intent     VARCHAR(40)  NOT NULL,
  answered   TINYINT(1)   NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_assistant_intent (intent),
  KEY ix_assistant_answered (answered, created_at),
  CONSTRAINT fk_assistant_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
