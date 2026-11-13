-- Migración 005: autenticación de API mediante login de usuario + Bearer token temporal.
-- El token real nunca se almacena: solo se persiste SHA-256.
-- Idempotente para instalaciones existentes.

CREATE TABLE IF NOT EXISTS api_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id BIGINT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    last_used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    ip_created VARCHAR(45) NULL,
    user_agent_created VARCHAR(255) NULL,
    CONSTRAINT fk_api_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX ix_api_tokens_user_active (usuario_id,revoked_at,expires_at),
    INDEX ix_api_tokens_expires (expires_at)
) ENGINE=InnoDB;

INSERT IGNORE INTO schema_migrations(version,description)
VALUES (5,'API con login de usuario y Bearer token temporal');
