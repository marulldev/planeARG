CREATE TABLE tokens_recuperacion (
  codToken BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codUsuario INT NOT NULL,
  tokenHash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
  venceEn DATETIME NOT NULL,
  creadoEn DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_tokens_recuperacion_usuario (codUsuario),
  INDEX idx_tokens_recuperacion_vence (venceEn),
  CONSTRAINT fk_tokens_recuperacion_usuario
    FOREIGN KEY (codUsuario) REFERENCES usuarios(codUsuario)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
