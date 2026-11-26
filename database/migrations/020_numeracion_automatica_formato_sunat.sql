-- 020: reserva automática de numeración de formato SUNAT, sin emitir CPE.
-- No modifica números históricos. Requiere 012 y 019.
CREATE TABLE IF NOT EXISTS sunat_numeros_reservados (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    documento_id BIGINT NULL,
    guia_id BIGINT NULL,
    ruc_emisor CHAR(11) NOT NULL,
    tipo_cpe CHAR(2) NOT NULL,
    serie CHAR(4) NOT NULL,
    correlativo BIGINT UNSIGNED NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_snr_doc(documento_id),
    UNIQUE KEY uq_snr_guia(guia_id),
    UNIQUE KEY uq_snr_fiscal(ruc_emisor,tipo_cpe,serie,correlativo),
    FOREIGN KEY(documento_id) REFERENCES documentos_cabecera(id) ON DELETE CASCADE,
    FOREIGN KEY(guia_id) REFERENCES guias_cabecera(id) ON DELETE CASCADE,
    CHECK ((documento_id IS NULL) <> (guia_id IS NULL))
) ENGINE=InnoDB;
INSERT IGNORE INTO schema_migrations(version,description)
VALUES(20,'Reserva automatica de series y correlativos de formato SUNAT');
