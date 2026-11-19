INSERT INTO `store_settings` (`setting_key`, `setting_value`)
VALUES ('origin_area_id', 'IDNP6IDNC148IDND838IDZ12110')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);