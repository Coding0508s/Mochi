-- Mochi3 레거시 테이블 구조를 Mochi4로 복사 (데이터 없음, sync 전 준비)
-- 실행: mysql -h HOST -u root -p < database/scripts/mochi4_clone_tables.sql

DROP TABLE IF EXISTS `Mochi4`.`S_CO_NewTarget_Detail`;
DROP TABLE IF EXISTS `Mochi4`.`S_CO_NewTarget`;
CREATE TABLE `Mochi4`.`S_CO_NewTarget` LIKE `Mochi3`.`S_CO_NewTarget`;
CREATE TABLE `Mochi4`.`S_CO_NewTarget_Detail` LIKE `Mochi3`.`S_CO_NewTarget_Detail`;

CREATE TABLE IF NOT EXISTS `Mochi4`.`employee` LIKE `Mochi3`.`employee`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`department` LIKE `Mochi3`.`department`;

CREATE TABLE IF NOT EXISTS `Mochi4`.`S_AccountName` LIKE `Mochi3`.`S_AccountName`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_Account_Information` LIKE `Mochi3`.`S_Account_Information`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_GSNumber` LIKE `Mochi3`.`S_GSNumber`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`Teachers` LIKE `Mochi3`.`Teachers`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_RetirementList` LIKE `Mochi3`.`S_RetirementList`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_TeacherMasterDB` LIKE `Mochi3`.`S_TeacherMasterDB`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_SupportInfo_Account` LIKE `Mochi3`.`S_SupportInfo_Account`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_Support_NewTeacher` LIKE `Mochi3`.`S_Support_NewTeacher`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_Support_LVA` LIKE `Mochi3`.`S_Support_LVA`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_Support_OnSite` LIKE `Mochi3`.`S_Support_OnSite`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_Support_OpenClass` LIKE `Mochi3`.`S_Support_OpenClass`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_SupportLittleSEED_ONLVA` LIKE `Mochi3`.`S_SupportLittleSEED_ONLVA`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_Support_U21` LIKE `Mochi3`.`S_Support_U21`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_Support_U31` LIKE `Mochi3`.`S_Support_U31`;
CREATE TABLE IF NOT EXISTS `Mochi4`.`S_SolutionConsulting` LIKE `Mochi3`.`S_SolutionConsulting`;

-- sync는 UPDATE만 하는 테이블: 빈 Mochi4에 최초 1회 데이터 복사
INSERT INTO `Mochi4`.`S_AccountName` SELECT * FROM `Mochi3`.`S_AccountName`;
INSERT INTO `Mochi4`.`S_GSNumber` SELECT * FROM `Mochi3`.`S_GSNumber`;
