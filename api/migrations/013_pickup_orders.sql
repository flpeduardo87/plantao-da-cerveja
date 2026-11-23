ALTER TABLE orders
  MODIFY delivery_type ENUM('immediate','scheduled','pickup') NOT NULL DEFAULT 'immediate';
