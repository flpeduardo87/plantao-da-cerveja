-- Push subscriptions (Web Push / VAPID)
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    endpoint   TEXT NOT NULL,
    p256dh     VARCHAR(512) NOT NULL,
    auth       VARCHAR(255) NOT NULL,
    user_agent VARCHAR(512),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_endpoint (endpoint(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
