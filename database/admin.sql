-- MySQL dump 10.13  Distrib 5.7.22, for Linux (x86_64)
--
-- Host: 127.0.0.1    Database: lara_shop
-- ------------------------------------------------------
-- Server version	5.7.22-0ubuntu18.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Dumping data for table `admin_menu`
--

LOCK TABLES `admin_menu` WRITE;
/*!40000 ALTER TABLE `admin_menu` DISABLE KEYS */;
INSERT INTO `admin_menu` VALUES (1,0,1,'Index','fa-bar-chart','/',NULL,NULL,NULL),(2,0,6,'Admin','fa-tasks','',NULL,NULL,'2019-05-11 16:45:39'),(3,2,7,'Users','fa-users','auth/users',NULL,NULL,'2019-05-11 16:45:39'),(4,2,8,'Roles','fa-user','auth/roles',NULL,NULL,'2019-05-11 16:45:39'),(5,2,9,'Permission','fa-ban','auth/permissions',NULL,NULL,'2019-05-11 16:45:39'),(6,2,10,'Menu','fa-bars','auth/menu',NULL,NULL,'2019-05-11 16:45:39'),(7,2,11,'Operation log','fa-history','auth/logs',NULL,NULL,'2019-05-11 16:45:39'),(8,0,2,'用户管理','fa-users','/users',NULL,'2019-04-20 16:41:26','2019-04-20 16:41:31'),(9,0,3,'商品管理','fa-cubes','/products',NULL,'2019-04-20 18:07:37','2019-04-20 18:07:43'),(10,0,4,'订单管理','fa-rmb','/orders',NULL,'2019-05-09 11:29:56','2019-05-09 11:30:00'),(11,0,5,'优惠券管理','fa-tags','/coupon_codes',NULL,'2019-05-11 16:45:30','2019-05-11 16:45:39');
/*!40000 ALTER TABLE `admin_menu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `admin_permissions`
--

LOCK TABLES `admin_permissions` WRITE;
/*!40000 ALTER TABLE `admin_permissions` DISABLE KEYS */;
INSERT INTO `admin_permissions` VALUES (1,'All permission','*','','*',NULL,NULL),(2,'Dashboard','dashboard','GET','/',NULL,NULL),(3,'Login','auth.login','','/auth/login\r\n/auth/logout',NULL,NULL),(4,'User setting','auth.setting','GET,PUT','/auth/setting',NULL,NULL),(5,'Auth management','auth.management','','/auth/roles\r\n/auth/permissions\r\n/auth/menu\r\n/auth/logs',NULL,NULL),(6,'用户管理','/users','','/users*','2019-04-20 16:45:56','2019-04-20 16:45:56'),(7,'商品管理','/products','','/products*','2019-05-11 22:57:31','2019-05-11 22:57:31'),(8,'优惠券管理','/coupon_codes','','/coupon_codes*','2019-05-11 22:57:58','2019-05-11 22:57:58'),(9,'订单管理','/orders','','/orders*','2019-05-11 22:58:20','2019-05-11 22:58:20');
/*!40000 ALTER TABLE `admin_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `admin_role_menu`
--

LOCK TABLES `admin_role_menu` WRITE;
/*!40000 ALTER TABLE `admin_role_menu` DISABLE KEYS */;
INSERT INTO `admin_role_menu` VALUES (1,2,NULL,NULL);
/*!40000 ALTER TABLE `admin_role_menu` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `admin_role_permissions`
--

LOCK TABLES `admin_role_permissions` WRITE;
/*!40000 ALTER TABLE `admin_role_permissions` DISABLE KEYS */;
INSERT INTO `admin_role_permissions` VALUES (1,1,NULL,NULL),(2,2,NULL,NULL),(2,3,NULL,NULL),(2,4,NULL,NULL),(2,6,NULL,NULL),(2,7,NULL,NULL),(2,8,NULL,NULL),(2,9,NULL,NULL);
/*!40000 ALTER TABLE `admin_role_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `admin_role_users`
--

LOCK TABLES `admin_role_users` WRITE;
/*!40000 ALTER TABLE `admin_role_users` DISABLE KEYS */;
INSERT INTO `admin_role_users` VALUES (1,1,NULL,NULL),(2,2,NULL,NULL);
/*!40000 ALTER TABLE `admin_role_users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `admin_roles`
--

LOCK TABLES `admin_roles` WRITE;
/*!40000 ALTER TABLE `admin_roles` DISABLE KEYS */;
INSERT INTO `admin_roles` VALUES (1,'Administrator','administrator','2019-04-19 18:00:28','2019-04-19 18:00:28'),(2,'运营','operator','2019-04-20 16:47:42','2019-04-20 16:47:42');
/*!40000 ALTER TABLE `admin_roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `admin_user_permissions`
--

LOCK TABLES `admin_user_permissions` WRITE;
/*!40000 ALTER TABLE `admin_user_permissions` DISABLE KEYS */;
/*!40000 ALTER TABLE `admin_user_permissions` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping data for table `admin_users`
--

LOCK TABLES `admin_users` WRITE;
/*!40000 ALTER TABLE `admin_users` DISABLE KEYS */;
INSERT INTO `admin_users` VALUES (1,'admin','$2y$10$8JIRsVvzyR9KtaH7oPSDD.oSTP7OXgX/AIkyh2mG2HaLRf9ECezHC','Administrator',NULL,'ynmGmJHR8mMr8xII4kluoKSy6L0Ni8YJcRZbOljUADvTIgcoz61y7ArVBdrN','2019-04-19 18:00:28','2019-04-19 18:00:28'),(2,'operator','$2y$10$Js1ztRKeiCLDRXsE776TJOqQNP2rgOXrBXGhJe9ipJ36LsoqBBkVm','运营',NULL,'y10M2on6jZvTzjuEd67ArMqdCCZZsLyJ85JtvuMzJ6nEYY4QOAIGaOPsOLCh','2019-04-20 16:48:50','2019-04-20 16:48:50');
/*!40000 ALTER TABLE `admin_users` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2019-05-15 10:28:20
