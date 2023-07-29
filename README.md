```sql
CREATE TABLE `turkish_trafic_accident`.`crash_period` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `period` VARCHAR(255) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE `unique_period` (`period`)
) ENGINE = InnoDB;

CREATE TABLE `crash_period` (
  `id` int NOT NULL,
  `period` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `crash_period`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_period` (`period`);

ALTER TABLE `crash_period`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;
```
