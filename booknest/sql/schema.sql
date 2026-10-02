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
  price          DECIMAL(8,2) NOT NULL DEFAULT 0.00,  -- replacement value, for staff only; never shown to members
  rating         DECIMAL(2,1) NOT NULL DEFAULT 0.0,
  published_year SMALLINT     NOT NULL,
  pages          SMALLINT UNSIGNED NOT NULL,
  stock          SMALLINT UNSIGNED NOT NULL DEFAULT 0,  -- copies the library owns; copies on the shelf are worked out live
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

-- Loans: one row per book borrowed. Late fees are never stored; they are worked out from the dates.
CREATE TABLE loans (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id         INT UNSIGNED NOT NULL,
  book_id         INT UNSIGNED NOT NULL,
  collection_date DATE NOT NULL,
  due_date        DATE NOT NULL,
  returned_date   DATE NULL,
  status          ENUM('reserved','active','overdue','returned') NOT NULL DEFAULT 'reserved',
  renewals        TINYINT UNSIGNED NOT NULL DEFAULT 0,
  fee_cleared_at  DATETIME NULL,
  created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY ix_loans_book_status (book_id, status),
  KEY ix_loans_user_status (user_id, status),
  KEY ix_loans_status_due (status, due_date),
  KEY ix_loans_created (created_at),
  CONSTRAINT fk_loans_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_loans_book FOREIGN KEY (book_id) REFERENCES books(id)
) ENGINE=InnoDB;

-- Queue for books with no copy on the shelf. "offered" means a copy is held for that member.
CREATE TABLE book_queue (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  book_id    INT UNSIGNED NOT NULL,
  user_id    INT UNSIGNED NOT NULL,
  queued_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status     ENUM('waiting','offered','expired','borrowed','left') NOT NULL DEFAULT 'waiting',
  offered_at DATETIME NULL,
  KEY ix_queue_book_status (book_id, status, queued_at),
  KEY ix_queue_user_status (user_id, status),
  CONSTRAINT fk_queue_book FOREIGN KEY (book_id) REFERENCES books(id) ON DELETE CASCADE,
  CONSTRAINT fk_queue_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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
