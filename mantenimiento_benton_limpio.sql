-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: mantenimiento_bacal
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `anuncios`
--

DROP TABLE IF EXISTS `anuncios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anuncios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `contenido` text NOT NULL,
  `tipo` enum('info','aviso','urgente','exito') NOT NULL DEFAULT 'info',
  `icono` varchar(50) DEFAULT 'megaphone',
  `sucursal_id` int(11) DEFAULT NULL,
  `rol_id` int(11) DEFAULT NULL,
  `fecha_inicio` date NOT NULL,
  `fecha_fin` date DEFAULT NULL COMMENT 'NULL = sin fecha límite',
  `fijado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = se fija arriba',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activo_vigencia` (`activo`,`fecha_inicio`,`fecha_fin`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_rol` (`rol_id`),
  KEY `fk_anun_creador` (`creado_por_id`),
  CONSTRAINT `fk_anun_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_anun_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_anun_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anuncios`
--

LOCK TABLES `anuncios` WRITE;
/*!40000 ALTER TABLE `anuncios` DISABLE KEYS */;
/*!40000 ALTER TABLE `anuncios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `anuncios_lecturas`
--

DROP TABLE IF EXISTS `anuncios_lecturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `anuncios_lecturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `anuncio_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `leido_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_anuncio_usuario` (`anuncio_id`,`usuario_id`),
  KEY `idx_usuario` (`usuario_id`),
  CONSTRAINT `fk_lect_anuncio` FOREIGN KEY (`anuncio_id`) REFERENCES `anuncios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lect_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `anuncios_lecturas`
--

LOCK TABLES `anuncios_lecturas` WRITE;
/*!40000 ALTER TABLE `anuncios_lecturas` DISABLE KEYS */;
/*!40000 ALTER TABLE `anuncios_lecturas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `areas`
--

DROP TABLE IF EXISTS `areas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#6B7280',
  `icono` varchar(50) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=114 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `areas`
--

LOCK TABLES `areas` WRITE;
/*!40000 ALTER TABLE `areas` DISABLE KEYS */;
/*!40000 ALTER TABLE `areas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auditoria_sistema`
--

DROP TABLE IF EXISTS `auditoria_sistema`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditoria_sistema` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` varchar(100) NOT NULL,
  `entidad` varchar(50) DEFAULT NULL,
  `entidad_id` int(11) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_accion` (`accion`),
  KEY `idx_fecha` (`creado_en`),
  CONSTRAINT `auditoria_sistema_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=255 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria_sistema`
--

LOCK TABLES `auditoria_sistema` WRITE;
/*!40000 ALTER TABLE `auditoria_sistema` DISABLE KEYS */;
/*!40000 ALTER TABLE `auditoria_sistema` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backups_realizados`
--

DROP TABLE IF EXISTS `backups_realizados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `backups_realizados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_archivo` varchar(255) NOT NULL,
  `tamano_bytes` bigint(20) NOT NULL DEFAULT 0,
  `tipo` enum('manual','automatico') NOT NULL DEFAULT 'manual',
  `realizado_por_id` int(11) DEFAULT NULL COMMENT 'Null si fue automatico',
  `notas` varchar(255) DEFAULT NULL,
  `exitoso` tinyint(1) NOT NULL DEFAULT 1,
  `mensaje_error` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_creado` (`creado_en`),
  KEY `fk_backup_usuario` (`realizado_por_id`),
  CONSTRAINT `fk_backup_usuario` FOREIGN KEY (`realizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backups_realizados`
--

LOCK TABLES `backups_realizados` WRITE;
/*!40000 ALTER TABLE `backups_realizados` DISABLE KEYS */;
/*!40000 ALTER TABLE `backups_realizados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#6B7280',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (13,'Mecánica',NULL,'#DC2626',1,'2026-05-28 16:10:57'),(14,'Eléctrica',NULL,'#F59E0B',1,'2026-05-28 16:10:57'),(15,'Electrónica/Control',NULL,'#7C3AED',1,'2026-05-28 16:10:57'),(16,'Hidráulica',NULL,'#3B82F6',1,'2026-05-28 16:10:57'),(17,'Neumática',NULL,'#10B981',1,'2026-05-28 16:10:57'),(18,'Refrigeración',NULL,'#06B6D4',1,'2026-05-28 16:10:57'),(19,'Vapor y calderas',NULL,'#991B1B',1,'2026-05-28 16:10:57'),(20,'Soldadura',NULL,'#EA580C',1,'2026-05-28 16:10:57'),(21,'Lubricación',NULL,'#84CC16',1,'2026-05-28 16:10:57'),(22,'Limpieza industrial',NULL,'#EC4899',1,'2026-05-28 16:10:57'),(23,'Edificación/Civil',NULL,'#71717a',1,'2026-05-28 16:10:57'),(24,'Seguridad industrial',NULL,'#EF4444',1,'2026-05-28 16:10:57'),(25,'Instrumentación',NULL,'#0891B2',1,'2026-05-28 16:10:57'),(26,'Tratamiento de agua',NULL,'#0284C7',1,'2026-05-28 16:10:57'),(27,'Otro',NULL,'#525252',1,'2026-05-28 16:10:57'),(28,'Prueba',NULL,'#0e40a4',1,'2026-06-15 19:06:01');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias_palabras_clave`
--

DROP TABLE IF EXISTS `categorias_palabras_clave`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias_palabras_clave` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `palabra` varchar(60) NOT NULL COMMENT 'Palabra o frase clave (lowercase, sin acentos)',
  `peso` int(11) NOT NULL DEFAULT 1 COMMENT 'Mayor peso = más específica',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cat_palabra` (`categoria_id`,`palabra`),
  KEY `idx_palabra` (`palabra`),
  CONSTRAINT `fk_kw_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=266 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias_palabras_clave`
--

LOCK TABLES `categorias_palabras_clave` WRITE;
/*!40000 ALTER TABLE `categorias_palabras_clave` DISABLE KEYS */;
/*!40000 ALTER TABLE `categorias_palabras_clave` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `comentario_reacciones`
--

DROP TABLE IF EXISTS `comentario_reacciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `comentario_reacciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `comentario_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `emoji` varchar(10) NOT NULL COMMENT 'Emoji unicode',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_react` (`comentario_id`,`usuario_id`,`emoji`),
  KEY `idx_comentario` (`comentario_id`),
  KEY `fk_react_usuario` (`usuario_id`),
  CONSTRAINT `fk_react_comentario` FOREIGN KEY (`comentario_id`) REFERENCES `incidencias_comentarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_react_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `comentario_reacciones`
--

LOCK TABLES `comentario_reacciones` WRITE;
/*!40000 ALTER TABLE `comentario_reacciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `comentario_reacciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion` (
  `clave` varchar(60) NOT NULL,
  `valor` text DEFAULT NULL,
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parámetros configurables del sistema';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion`
--

LOCK TABLES `configuracion` WRITE;
/*!40000 ALTER TABLE `configuracion` DISABLE KEYS */;
INSERT INTO `configuracion` VALUES ('odometro_umbral_dias','30','2026-06-23 00:43:47');
/*!40000 ALTER TABLE `configuracion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `configuracion_notificaciones`
--

DROP TABLE IF EXISTS `configuracion_notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion_notificaciones` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `smtp_host` varchar(255) DEFAULT NULL COMMENT 'Servidor SMTP, ej: mail.tudominio.com',
  `smtp_port` smallint(5) unsigned DEFAULT 587 COMMENT '465=SSL, 587=TLS, 25=sin cifrado',
  `smtp_seguridad` enum('tls','ssl','none') DEFAULT 'tls',
  `smtp_usuario` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL COMMENT 'Contraseña en texto plano (idealmente cifrada a futuro)',
  `smtp_from_email` varchar(255) DEFAULT NULL COMMENT 'Dirección remitente',
  `smtp_from_nombre` varchar(150) DEFAULT 'Bitácora Mantenimiento',
  `smtp_activo` tinyint(1) NOT NULL DEFAULT 0,
  `telegram_bot_token` varchar(255) DEFAULT NULL COMMENT 'Token del bot de @BotFather',
  `telegram_activo` tinyint(1) NOT NULL DEFAULT 0,
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `configuracion_notificaciones`
--

LOCK TABLES `configuracion_notificaciones` WRITE;
/*!40000 ALTER TABLE `configuracion_notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `configuracion_notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipo_componentes`
--

DROP TABLE IF EXISTS `equipo_componentes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipo_componentes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipo_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL COMMENT 'ej. Motor eléctrico, Banda B-50, Filtro de aceite',
  `tipo` varchar(80) DEFAULT NULL COMMENT 'ej. Motor, Sensor, Filtro, Banda',
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_parte` varchar(100) DEFAULT NULL COMMENT 'Part number del fabricante',
  `numero_serie` varchar(100) DEFAULT NULL,
  `fecha_instalacion` date DEFAULT NULL,
  `vida_util_meses` int(11) DEFAULT NULL COMMENT 'Vida útil estimada en meses',
  `proxima_revision` date DEFAULT NULL,
  `costo_unitario` decimal(10,2) DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `estado` enum('operando','desgaste','falla','reemplazado','retirado') NOT NULL DEFAULT 'operando',
  `criticidad` enum('baja','media','alta','critica') NOT NULL DEFAULT 'media' COMMENT 'Impacto de su falla en el equipo padre',
  `posicion` varchar(100) DEFAULT NULL COMMENT 'Ubicación física dentro del equipo (ej. lado izquierdo, etapa 2)',
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_id` int(11) DEFAULT NULL,
  `actualizado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipo` (`equipo_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_proxima_revision` (`proxima_revision`),
  KEY `fk_comp_proveedor` (`proveedor_id`),
  KEY `fk_comp_creador` (`creado_por_id`),
  KEY `fk_comp_actualizador` (`actualizado_por_id`),
  CONSTRAINT `fk_comp_actualizador` FOREIGN KEY (`actualizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comp_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comp_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comp_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipo_componentes`
--

LOCK TABLES `equipo_componentes` WRITE;
/*!40000 ALTER TABLE `equipo_componentes` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipo_componentes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipo_componentes_historial`
--

DROP TABLE IF EXISTS `equipo_componentes_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipo_componentes_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `componente_id` int(11) NOT NULL,
  `accion` enum('instalado','reemplazado','reparado','retirado','revisado') NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `incidencia_id` int(11) DEFAULT NULL COMMENT 'Si el cambio fue por una incidencia/orden',
  `usuario_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_componente` (`componente_id`,`creado_en`),
  KEY `idx_incidencia` (`incidencia_id`),
  KEY `fk_comp_hist_usuario` (`usuario_id`),
  CONSTRAINT `fk_comp_hist_comp` FOREIGN KEY (`componente_id`) REFERENCES `equipo_componentes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comp_hist_inc` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_comp_hist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipo_componentes_historial`
--

LOCK TABLES `equipo_componentes_historial` WRITE;
/*!40000 ALTER TABLE `equipo_componentes_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipo_componentes_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipo_fotos`
--

DROP TABLE IF EXISTS `equipo_fotos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipo_fotos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipo_id` int(11) NOT NULL,
  `ruta` varchar(255) NOT NULL COMMENT 'Ruta relativa (assets/equipos/...)',
  `descripcion` varchar(255) DEFAULT NULL,
  `es_portada` tinyint(1) NOT NULL DEFAULT 0,
  `subido_por_id` int(11) DEFAULT NULL,
  `tamano_bytes` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipo` (`equipo_id`),
  KEY `fk_foto_usuario` (`subido_por_id`),
  CONSTRAINT `fk_foto_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_foto_usuario` FOREIGN KEY (`subido_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipo_fotos`
--

LOCK TABLES `equipo_fotos` WRITE;
/*!40000 ALTER TABLE `equipo_fotos` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipo_fotos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipo_transferencias`
--

DROP TABLE IF EXISTS `equipo_transferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipo_transferencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipo_id` int(11) NOT NULL,
  `sucursal_origen_id` int(11) DEFAULT NULL COMMENT 'Null si era equipo nuevo recien llegado',
  `sucursal_destino_id` int(11) NOT NULL,
  `area_origen_id` int(11) DEFAULT NULL,
  `area_destino_id` int(11) DEFAULT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `fecha_transferencia` date NOT NULL,
  `realizado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipo` (`equipo_id`),
  KEY `idx_fecha` (`fecha_transferencia`),
  KEY `fk_trans_origen` (`sucursal_origen_id`),
  KEY `fk_trans_destino` (`sucursal_destino_id`),
  KEY `fk_trans_area_origen` (`area_origen_id`),
  KEY `fk_trans_area_destino` (`area_destino_id`),
  KEY `fk_trans_usuario` (`realizado_por_id`),
  CONSTRAINT `fk_trans_area_destino` FOREIGN KEY (`area_destino_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trans_area_origen` FOREIGN KEY (`area_origen_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trans_destino` FOREIGN KEY (`sucursal_destino_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `fk_trans_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_trans_origen` FOREIGN KEY (`sucursal_origen_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_trans_usuario` FOREIGN KEY (`realizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipo_transferencias`
--

LOCK TABLES `equipo_transferencias` WRITE;
/*!40000 ALTER TABLE `equipo_transferencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipo_transferencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `equipos`
--

DROP TABLE IF EXISTS `equipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `equipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo_inventario` varchar(50) NOT NULL,
  `nombre` varchar(150) NOT NULL,
  `tipo` varchar(50) DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `sucursal_id` int(11) NOT NULL,
  `planta_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `fecha_compra` date DEFAULT NULL,
  `costo_compra` decimal(12,2) DEFAULT NULL,
  `vida_util_meses` int(11) DEFAULT NULL COMMENT 'Vida util estimada en meses (60 = 5 años)',
  `fecha_baja` date DEFAULT NULL,
  `motivo_baja` varchar(255) DEFAULT NULL,
  `ubicacion` varchar(255) DEFAULT NULL,
  `responsable_id` int(11) DEFAULT NULL,
  `fecha_adquisicion` date DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `estado_vida` enum('nuevo','en_uso','en_reparacion','dado_de_baja') NOT NULL DEFAULT 'en_uso',
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `pos_x` decimal(5,2) DEFAULT NULL COMMENT '% desde el borde izquierdo',
  `pos_y` decimal(5,2) DEFAULT NULL COMMENT '% desde el borde superior',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo_inventario` (`codigo_inventario`),
  KEY `area_id` (`area_id`),
  KEY `responsable_id` (`responsable_id`),
  KEY `idx_sucursal_area` (`sucursal_id`,`area_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `fk_equipo_proveedor` (`proveedor_id`),
  KEY `idx_pos` (`pos_x`,`pos_y`),
  KEY `fk_equipo_planta` (`planta_id`),
  CONSTRAINT `equipos_ibfk_1` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `equipos_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `equipos_ibfk_3` FOREIGN KEY (`responsable_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_equipo_planta` FOREIGN KEY (`planta_id`) REFERENCES `sucursal_plantas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_equipo_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=167 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `equipos`
--

LOCK TABLES `equipos` WRITE;
/*!40000 ALTER TABLE `equipos` DISABLE KEYS */;
/*!40000 ALTER TABLE `equipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `estados`
--

DROP TABLE IF EXISTS `estados`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `estados` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `orden` int(11) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#6B7280',
  `es_inicial` tinyint(1) NOT NULL DEFAULT 0,
  `es_final` tinyint(1) NOT NULL DEFAULT 0,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `estados`
--

LOCK TABLES `estados` WRITE;
/*!40000 ALTER TABLE `estados` DISABLE KEYS */;
INSERT INTO `estados` VALUES (1,'Abierta',1,'#DC2626',1,0,'Recién registrada, sin atender',1),(2,'Asignada',2,'#EA580C',0,0,'Asignada a un técnico',1),(3,'En proceso',3,'#D97706',0,0,'Siendo atendida activamente',1),(4,'En espera de refacción',4,'#8B5CF6',0,0,'Esperando pieza para continuar',1),(5,'En espera',5,'#6B7280',0,0,'Esperando información o terceros',1),(6,'Resuelta',6,'#0EA5E9',0,0,'Solucionada, pendiente de confirmación',1),(7,'Completada',7,'#16A34A',0,1,'Confirmada y cerrada',1),(8,'Cancelada',8,'#6B7280',0,1,'Anulada sin resolución',1);
/*!40000 ALTER TABLE `estados` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_categorias_gasto`
--

DROP TABLE IF EXISTS `flotilla_categorias_gasto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_categorias_gasto` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `color` varchar(7) NOT NULL DEFAULT '#6B7280',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorías de gasto vehicular';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_categorias_gasto`
--

LOCK TABLES `flotilla_categorias_gasto` WRITE;
/*!40000 ALTER TABLE `flotilla_categorias_gasto` DISABLE KEYS */;
INSERT INTO `flotilla_categorias_gasto` VALUES (1,'Combustible','#F59E0B',1),(2,'Mantenimiento','#3B82F6',1),(3,'Refacciones','#8B5CF6',1),(4,'Neumáticos','#10B981',1),(5,'Seguro','#06B6D4',1),(6,'Tenencia / Trámites','#6366F1',1),(7,'Multas','#EF4444',1),(8,'Lavado / Limpieza','#84CC16',1),(9,'Peajes / Casetas','#F97316',1),(10,'Siniestro','#DC2626',1),(11,'Renta de vehículo','#A855F7',1),(12,'Otro','#71717A',1);
/*!40000 ALTER TABLE `flotilla_categorias_gasto` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_checklist_items`
--

DROP TABLE IF EXISTS `flotilla_checklist_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_checklist_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria` varchar(60) NOT NULL COMMENT 'Mecánico, Luces, Neumáticos, Cabina, Documentación',
  `nombre` varchar(100) NOT NULL,
  `obligatorio` tinyint(1) NOT NULL DEFAULT 1,
  `orden` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de ítems a revisar en el checklist diario';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_checklist_items`
--

LOCK TABLES `flotilla_checklist_items` WRITE;
/*!40000 ALTER TABLE `flotilla_checklist_items` DISABLE KEYS */;
INSERT INTO `flotilla_checklist_items` VALUES (1,'Mecánico','Nivel de aceite del motor',1,1,1),(2,'Mecánico','Nivel de agua / anticongelante',1,2,1),(3,'Mecánico','Nivel de líquido de frenos',1,3,1),(4,'Mecánico','Nivel de dirección hidráulica',0,4,1),(5,'Mecánico','Fugas visibles (aceite, agua, gas)',1,5,1),(6,'Mecánico','Funcionamiento de frenos',1,6,1),(7,'Mecánico','Funcionamiento del freno de mano',1,7,1),(8,'Neumáticos','Presión de llantas (visual)',1,10,1),(9,'Neumáticos','Desgaste de llantas',1,11,1),(10,'Neumáticos','Estado de llanta de refacción',0,12,1),(11,'Neumáticos','Tuercas y birlos firmes',1,13,1),(12,'Luces','Faros delanteros',1,20,1),(13,'Luces','Calaveras traseras',1,21,1),(14,'Luces','Cuartos y direccionales',1,22,1),(15,'Luces','Luz de reversa',1,23,1),(16,'Luces','Luces de tablero sin alertas',1,24,1),(17,'Carrocería','Espejos en buen estado',1,30,1),(18,'Carrocería','Parabrisas sin roturas',1,31,1),(19,'Carrocería','Puertas y seguros funcionando',1,32,1),(20,'Carrocería','Sin daños visibles nuevos',1,33,1),(21,'Cabina','Cinturones de seguridad',1,40,1),(22,'Cabina','Bocina funcional',0,41,1),(23,'Cabina','Limpiabrisas funcionando',0,42,1),(24,'Refrigeración','Temperatura dentro de rango',0,50,1),(25,'Refrigeración','Unidad de frío sin alarmas',0,51,1),(26,'Refrigeración','Sello de puertas íntegro',0,52,1),(27,'Documentación','Tarjeta de circulación a bordo',1,60,1),(28,'Documentación','Póliza de seguro a bordo',1,61,1),(29,'Documentación','Extintor vigente',1,62,1),(30,'Documentación','Triángulos / señales emergencia',0,63,1);
/*!40000 ALTER TABLE `flotilla_checklist_items` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_checklist_respuestas`
--

DROP TABLE IF EXISTS `flotilla_checklist_respuestas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_checklist_respuestas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `checklist_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `resultado` enum('ok','observacion','falla') NOT NULL DEFAULT 'ok',
  `nota` varchar(300) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_checklist_item` (`checklist_id`,`item_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `flotilla_checklist_respuestas_ibfk_1` FOREIGN KEY (`checklist_id`) REFERENCES `flotilla_checklists` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flotilla_checklist_respuestas_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `flotilla_checklist_items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Respuestas por ítem del checklist';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_checklist_respuestas`
--

LOCK TABLES `flotilla_checklist_respuestas` WRITE;
/*!40000 ALTER TABLE `flotilla_checklist_respuestas` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_checklist_respuestas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_checklists`
--

DROP TABLE IF EXISTS `flotilla_checklists`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_checklists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehiculo_id` int(11) NOT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `viaje_id` int(11) DEFAULT NULL COMMENT 'Asociado a un viaje si aplica',
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  `tipo` enum('pre_viaje','post_viaje','diario') NOT NULL DEFAULT 'pre_viaje',
  `km_odometro` int(11) DEFAULT NULL,
  `resultado` enum('ok','observaciones','no_apto') NOT NULL DEFAULT 'ok',
  `observaciones_gen` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conductor_id` (`conductor_id`),
  KEY `viaje_id` (`viaje_id`),
  KEY `idx_vehiculo_fecha` (`vehiculo_id`,`fecha`),
  KEY `idx_resultado` (`resultado`),
  CONSTRAINT `flotilla_checklists_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `flotilla_vehiculos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flotilla_checklists_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `flotilla_conductores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_checklists_ibfk_3` FOREIGN KEY (`viaje_id`) REFERENCES `flotilla_viajes` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabecera del checklist de revisión diaria del vehículo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_checklists`
--

LOCK TABLES `flotilla_checklists` WRITE;
/*!40000 ALTER TABLE `flotilla_checklists` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_checklists` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_combustible`
--

DROP TABLE IF EXISTS `flotilla_combustible`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_combustible` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehiculo_id` int(11) NOT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `fecha` datetime NOT NULL,
  `km_odometro` int(11) NOT NULL,
  `litros` decimal(8,3) NOT NULL,
  `precio_litro` decimal(6,3) NOT NULL,
  `total` decimal(10,2) GENERATED ALWAYS AS (`litros` * `precio_litro`) STORED,
  `tipo_combustible` enum('gasolina_regular','gasolina_premium','diesel','gas') NOT NULL DEFAULT 'diesel',
  `estacion` varchar(100) DEFAULT NULL,
  `estacion_id` int(11) DEFAULT NULL,
  `ticket_numero` varchar(50) DEFAULT NULL,
  `recibo_url` varchar(255) DEFAULT NULL,
  `es_tanque_lleno` tinyint(1) NOT NULL DEFAULT 1,
  `km_recorridos` int(11) DEFAULT NULL,
  `rendimiento_kml` decimal(6,3) DEFAULT NULL COMMENT 'km por litro',
  `notas` varchar(300) DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conductor_id` (`conductor_id`),
  KEY `creado_por` (`creado_por`),
  KEY `idx_vehiculo_fecha` (`vehiculo_id`,`fecha`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `flotilla_combustible_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `flotilla_vehiculos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flotilla_combustible_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `flotilla_conductores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_combustible_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de cargas de combustible por vehículo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_combustible`
--

LOCK TABLES `flotilla_combustible` WRITE;
/*!40000 ALTER TABLE `flotilla_combustible` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_combustible` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_conductores`
--

DROP TABLE IF EXISTS `flotilla_conductores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_conductores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) DEFAULT NULL COMMENT 'Vínculo con usuarios del sistema si aplica',
  `nombre_completo` varchar(150) NOT NULL,
  `numero_empleado` varchar(30) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `sucursal_id` int(11) DEFAULT NULL,
  `licencia_numero` varchar(50) DEFAULT NULL,
  `licencia_tipo` varchar(20) DEFAULT NULL COMMENT 'A, B, C, D, E, ó combinación',
  `licencia_vence` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `notas` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `sucursal_id` (`sucursal_id`),
  KEY `idx_activo` (`activo`),
  CONSTRAINT `flotilla_conductores_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_conductores_ibfk_2` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conductores de la flotilla';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_conductores`
--

LOCK TABLES `flotilla_conductores` WRITE;
/*!40000 ALTER TABLE `flotilla_conductores` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_conductores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_documentos`
--

DROP TABLE IF EXISTS `flotilla_documentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_documentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehiculo_id` int(11) DEFAULT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `tipo_id` int(11) NOT NULL,
  `numero_documento` varchar(100) DEFAULT NULL,
  `proveedor` varchar(100) DEFAULT NULL COMMENT 'Aseguradora, gobierno, etc.',
  `fecha_inicio` date DEFAULT NULL,
  `fecha_vence` date DEFAULT NULL,
  `monto` decimal(10,2) DEFAULT NULL COMMENT 'Costo del documento (póliza, tenencia, etc.)',
  `archivo_url` varchar(255) DEFAULT NULL COMMENT 'PDF escaneado',
  `estado` enum('vigente','por_vencer','vencido','cancelado') NOT NULL DEFAULT 'vigente',
  `notas` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conductor_id` (`conductor_id`),
  KEY `tipo_id` (`tipo_id`),
  KEY `creado_por` (`creado_por`),
  KEY `idx_fecha_vence` (`fecha_vence`),
  KEY `idx_estado` (`estado`),
  KEY `idx_vehiculo` (`vehiculo_id`),
  CONSTRAINT `flotilla_documentos_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `flotilla_vehiculos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flotilla_documentos_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `flotilla_conductores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flotilla_documentos_ibfk_3` FOREIGN KEY (`tipo_id`) REFERENCES `flotilla_tipos_documento` (`id`),
  CONSTRAINT `flotilla_documentos_ibfk_4` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos con fecha de vencimiento: seguro, tarjeta circulación, verificación';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_documentos`
--

LOCK TABLES `flotilla_documentos` WRITE;
/*!40000 ALTER TABLE `flotilla_documentos` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_documentos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_estaciones`
--

DROP TABLE IF EXISTS `flotilla_estaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_estaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(120) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB AUTO_INCREMENT=128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de estaciones/gasolineras de carga';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_estaciones`
--

LOCK TABLES `flotilla_estaciones` WRITE;
/*!40000 ALTER TABLE `flotilla_estaciones` DISABLE KEYS */;
INSERT INTO `flotilla_estaciones` VALUES (1,'TIJ-LA MESA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(2,'TIJ-EL LAGO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(3,'TIJ-ALVAREZ EL RUBI','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(4,'TIJ-DIAZ ORDAZ LIMON','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(5,'TIJ-CUCAPAH 2','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(6,'TIJ-VILLAFLORESTA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(7,'TIJ-PRADO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(8,'TIJ-OBRERA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(9,'TIJ-EL TECOLOTE','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(10,'TIJ-VIA RAPIDA ORIENTE','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(11,'TIJ-5 y 10','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(12,'TIJ-LAS AGUAS','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(13,'TIJ-SERVICIO AZTECA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(14,'TIJ-HIPODROMO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(15,'TIJ-MARINERO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(16,'TIJ-LIBRAMIENTO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(17,'TIJ-BELLAS ARTES','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(18,'TIJ-CLINICA 27','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(19,'TIJ-CUCAPAH 1','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(20,'TIJ-TOMAS AQUINO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(21,'RST-ROSARITO NORTE','Playas de Rosarito, B.C.',1,NULL,'2026-06-23 15:46:37'),(22,'TIJ-OTAY GARITA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(23,'TIJ-EL CAÑAVERAL','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(24,'TIJ-LIBERTAD AEROPUERTO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(25,'TIJ-LOMA DORADA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(26,'TIJ-GATO BRONCO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(27,'TIJ-TERRAZAS DEL VALLE','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(28,'TIJ-SANTA FE','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(29,'TIJ-PACIFICO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(30,'TIJ-LOS ALAMOS','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(31,'TIJ-EL FLORIDO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(32,'TIJ-CERRO DE LAS ABEJAS','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(33,'TCT-ESMERALDA','Baja California',1,NULL,'2026-06-23 15:46:37'),(34,'TIJ-JESUS MARIA ( Rael )','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(35,'TIJ-LAS FUENTES','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(36,'TIJ-20 NOVIEMBRE','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(37,'TIJ-VIA RAPIDA PONIENTE','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(38,'TIJ-HACIENDA SANTA MARIA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(39,'TIJ-MARIANO ( Monumento )','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(40,'TIJ-AEROPUERTO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(41,'TIJ-TERAN TERAN','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(42,'RST-ROSARITO LIBRE','Playas de Rosarito, B.C.',1,NULL,'2026-06-23 15:46:37'),(43,'TIJ-PASEO 2000','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(44,'TIJ-EL TRIANGULO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(45,'TIJ-LA VILLA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(46,'TIJ-OJO DE AGUA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(47,'TIJ-OTAY TECNOLOGICO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(48,'TCT-TECATE ESTADIO','Baja California',1,NULL,'2026-06-23 15:46:37'),(49,'TIJ-CALLE 9a. TIJUANA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(50,'TIJ-CASA BLANCA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(51,'TIJ-LA GLORIA','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(52,'TIJ-MELCHOR OCAMPO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(53,'TIJ-EL MEXICANO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(54,'TCT-TECATE INDUSTRIAL','Baja California',1,NULL,'2026-06-23 15:46:37'),(55,'TIJ-LIBERTAD SERDAN','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(56,'TIJ-FLAMINGOS','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(57,'TIJ-VILLA DEL PRADO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(58,'TIJ-OTAY INDUSTRIAL','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(59,'TIJ-JIBARITO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(60,'TIJ-LAS PALMERAS','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(61,'TIJ-LADERAS DE MONTERREY','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(62,'TIJ-CAÑON DEL SAINZ','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(63,'TCT-DEFENSORES','Baja California',1,NULL,'2026-06-23 15:46:37'),(64,'TIJ-RANCHO CASIAN','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(65,'TIJ-RIO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(66,'RST-PUERTO NUEVO','Playas de Rosarito, B.C.',1,NULL,'2026-06-23 15:46:37'),(67,'TIJ-LA POSTAL','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(68,'TIJ-WASHMOBILE','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(69,'TIJ-EL PEDREGAL','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(70,'TIJ-LINDA VISTA CALLE 2da.','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37'),(71,'RST-ROSARITO QUINTA PLAZA','Playas de Rosarito, B.C.',1,NULL,'2026-06-23 15:46:37'),(72,'TIJ-JARDIN DORADO','Tijuana, B.C.',1,NULL,'2026-06-23 15:46:37');
/*!40000 ALTER TABLE `flotilla_estaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_gastos`
--

DROP TABLE IF EXISTS `flotilla_gastos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_gastos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehiculo_id` int(11) NOT NULL,
  `categoria_id` int(11) NOT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `fecha` date NOT NULL,
  `concepto` varchar(200) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `proveedor` varchar(100) DEFAULT NULL,
  `numero_factura` varchar(60) DEFAULT NULL,
  `archivo_url` varchar(255) DEFAULT NULL COMMENT 'Comprobante / factura',
  `km_odometro` int(11) DEFAULT NULL,
  `combustible_id` int(11) DEFAULT NULL,
  `siniestro_id` int(11) DEFAULT NULL,
  `multa_id` int(11) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conductor_id` (`conductor_id`),
  KEY `creado_por` (`creado_por`),
  KEY `idx_vehiculo_fecha` (`vehiculo_id`,`fecha`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `flotilla_gastos_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `flotilla_vehiculos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flotilla_gastos_ibfk_2` FOREIGN KEY (`categoria_id`) REFERENCES `flotilla_categorias_gasto` (`id`),
  CONSTRAINT `flotilla_gastos_ibfk_3` FOREIGN KEY (`conductor_id`) REFERENCES `flotilla_conductores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_gastos_ibfk_4` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2144 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Todos los gastos asociados a un vehículo (combustible, mantenimiento, multas, etc.)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_gastos`
--

LOCK TABLES `flotilla_gastos` WRITE;
/*!40000 ALTER TABLE `flotilla_gastos` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_gastos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_mant_historial`
--

DROP TABLE IF EXISTS `flotilla_mant_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_mant_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehiculo_id` int(11) NOT NULL,
  `programa_id` int(11) DEFAULT NULL COMMENT 'NULL si fue mantenimiento no programado',
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha` date NOT NULL,
  `km_odometro` int(11) NOT NULL,
  `taller` varchar(100) DEFAULT NULL,
  `tecnico` varchar(100) DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT NULL,
  `numero_orden` varchar(60) DEFAULT NULL,
  `archivo_url` varchar(255) DEFAULT NULL COMMENT 'Factura u orden de trabajo',
  `proximo_km` int(11) DEFAULT NULL,
  `proxima_fecha` date DEFAULT NULL,
  `incidencia_id` int(11) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `programa_id` (`programa_id`),
  KEY `incidencia_id` (`incidencia_id`),
  KEY `creado_por` (`creado_por`),
  KEY `idx_vehiculo_fecha` (`vehiculo_id`,`fecha`),
  KEY `idx_proximo_km` (`proximo_km`),
  KEY `idx_proxima_fecha` (`proxima_fecha`),
  CONSTRAINT `flotilla_mant_historial_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `flotilla_vehiculos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flotilla_mant_historial_ibfk_2` FOREIGN KEY (`programa_id`) REFERENCES `flotilla_mant_programas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_mant_historial_ibfk_3` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_mant_historial_ibfk_4` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de mantenimientos preventivos y correctivos ejecutados';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_mant_historial`
--

LOCK TABLES `flotilla_mant_historial` WRITE;
/*!40000 ALTER TABLE `flotilla_mant_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_mant_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_mant_programas`
--

DROP TABLE IF EXISTS `flotilla_mant_programas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_mant_programas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `intervalo_km` int(11) DEFAULT NULL COMMENT 'Ejecutar cada X km',
  `intervalo_dias` int(11) DEFAULT NULL COMMENT 'Ejecutar cada X días',
  `aplica_tipo_vehiculo_id` int(11) DEFAULT NULL COMMENT 'NULL = aplica a todos los tipos',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `aplica_tipo_vehiculo_id` (`aplica_tipo_vehiculo_id`),
  CONSTRAINT `flotilla_mant_programas_ibfk_1` FOREIGN KEY (`aplica_tipo_vehiculo_id`) REFERENCES `flotilla_tipos_vehiculo` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Programas de mantenimiento preventivo: qué hacer y cada cuánto km/días';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_mant_programas`
--

LOCK TABLES `flotilla_mant_programas` WRITE;
/*!40000 ALTER TABLE `flotilla_mant_programas` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_mant_programas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_multas`
--

DROP TABLE IF EXISTS `flotilla_multas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_multas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehiculo_id` int(11) NOT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `fecha_infraccion` date NOT NULL,
  `fecha_vence_pago` date DEFAULT NULL,
  `autoridad` varchar(100) DEFAULT NULL COMMENT 'Tránsito Municipal, Policía Federal, etc.',
  `numero_infraccion` varchar(60) DEFAULT NULL,
  `motivo` varchar(200) NOT NULL,
  `monto_original` decimal(10,2) NOT NULL,
  `monto_con_descuento` decimal(10,2) DEFAULT NULL COMMENT 'Si hay descuento por pronto pago',
  `monto_pagado` decimal(10,2) DEFAULT NULL,
  `fecha_pago` date DEFAULT NULL,
  `responsable` enum('conductor','empresa','en_disputa') NOT NULL DEFAULT 'en_disputa',
  `estado` enum('pendiente','pagada','impugnada','cancelada') NOT NULL DEFAULT 'pendiente',
  `archivo_url` varchar(255) DEFAULT NULL COMMENT 'Foto o PDF de la infracción',
  `notas` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conductor_id` (`conductor_id`),
  KEY `creado_por` (`creado_por`),
  KEY `idx_vehiculo` (`vehiculo_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha_vence` (`fecha_vence_pago`),
  CONSTRAINT `flotilla_multas_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `flotilla_vehiculos` (`id`),
  CONSTRAINT `flotilla_multas_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `flotilla_conductores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_multas_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Infracciones de tránsito: multas, monto, responsable, estado de pago';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_multas`
--

LOCK TABLES `flotilla_multas` WRITE;
/*!40000 ALTER TABLE `flotilla_multas` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_multas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_neumaticos`
--

DROP TABLE IF EXISTS `flotilla_neumaticos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_neumaticos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehiculo_id` int(11) DEFAULT NULL COMMENT 'NULL si está en almacén o desechado',
  `marca` varchar(60) DEFAULT NULL,
  `medida` varchar(30) DEFAULT NULL COMMENT 'Ej: 275/70R22.5',
  `numero_serie` varchar(60) DEFAULT NULL,
  `posicion` varchar(30) DEFAULT NULL COMMENT 'del_izq, del_der, tra_izq_ext, tra_izq_int, etc.',
  `fecha_instalacion` date DEFAULT NULL,
  `km_instalacion` int(11) DEFAULT NULL,
  `km_vida_util` int(11) DEFAULT NULL COMMENT 'Km de vida útil estimada',
  `km_acumulados` int(11) NOT NULL DEFAULT 0,
  `profundidad_mm` decimal(4,2) DEFAULT NULL COMMENT 'Última medición de profundidad de la banda',
  `ultima_inspeccion` date DEFAULT NULL,
  `estado` enum('en_uso','almacen','desechado') NOT NULL DEFAULT 'en_uso',
  `notas` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_vehiculo` (`vehiculo_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `flotilla_neumaticos_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `flotilla_vehiculos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Control individual de neumáticos: posición, desgaste, historial';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_neumaticos`
--

LOCK TABLES `flotilla_neumaticos` WRITE;
/*!40000 ALTER TABLE `flotilla_neumaticos` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_neumaticos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_odometro_historial`
--

DROP TABLE IF EXISTS `flotilla_odometro_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_odometro_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehiculo_id` int(11) NOT NULL,
  `km` int(11) NOT NULL,
  `km_anterior` int(11) DEFAULT NULL,
  `origen` varchar(20) NOT NULL DEFAULT 'manual',
  `usuario_id` int(11) DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL,
  `leido_en` datetime NOT NULL DEFAULT current_timestamp(),
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_veh_fecha` (`vehiculo_id`,`leido_en`),
  CONSTRAINT `fk_odo_veh` FOREIGN KEY (`vehiculo_id`) REFERENCES `flotilla_vehiculos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lecturas del odómetro por vehículo (para medir uso y antigüedad)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_odometro_historial`
--

LOCK TABLES `flotilla_odometro_historial` WRITE;
/*!40000 ALTER TABLE `flotilla_odometro_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_odometro_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_siniestros`
--

DROP TABLE IF EXISTS `flotilla_siniestros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_siniestros` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehiculo_id` int(11) NOT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `fecha` datetime NOT NULL,
  `tipo` enum('colision','robo_parcial','robo_total','vandalismo','fenomeno_natural','otro') NOT NULL DEFAULT 'colision',
  `descripcion` text NOT NULL,
  `lugar` varchar(200) DEFAULT NULL,
  `hay_terceros` tinyint(1) NOT NULL DEFAULT 0,
  `descripcion_terceros` text DEFAULT NULL,
  `numero_siniestro_aseg` varchar(60) DEFAULT NULL COMMENT 'Número de siniestro de la aseguradora',
  `aseguradora` varchar(100) DEFAULT NULL,
  `fecha_reporte_aseguradora` date DEFAULT NULL,
  `monto_deducible` decimal(10,2) DEFAULT NULL,
  `monto_reparacion` decimal(10,2) DEFAULT NULL,
  `monto_cubierto_seguro` decimal(10,2) DEFAULT NULL,
  `estado` enum('reportado','en_proceso','resuelto','cerrado') NOT NULL DEFAULT 'reportado',
  `fecha_resolucion` date DEFAULT NULL,
  `fotos` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Array de URLs de fotografías' CHECK (json_valid(`fotos`)),
  `notas` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `conductor_id` (`conductor_id`),
  KEY `creado_por` (`creado_por`),
  KEY `idx_vehiculo` (`vehiculo_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `flotilla_siniestros_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `flotilla_vehiculos` (`id`),
  CONSTRAINT `flotilla_siniestros_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `flotilla_conductores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_siniestros_ibfk_3` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de siniestros, accidentes e incidentes viales';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_siniestros`
--

LOCK TABLES `flotilla_siniestros` WRITE;
/*!40000 ALTER TABLE `flotilla_siniestros` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_siniestros` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_tipos_documento`
--

DROP TABLE IF EXISTS `flotilla_tipos_documento`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_tipos_documento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL,
  `aplica_vehiculo` tinyint(1) NOT NULL DEFAULT 1,
  `aplica_conductor` tinyint(1) NOT NULL DEFAULT 0,
  `dias_alerta` int(11) NOT NULL DEFAULT 30 COMMENT 'Días de anticipación para alertar antes del vencimiento',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tipos de documento: seguro, tarjeta circulación, verificación, licencia, etc.';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_tipos_documento`
--

LOCK TABLES `flotilla_tipos_documento` WRITE;
/*!40000 ALTER TABLE `flotilla_tipos_documento` DISABLE KEYS */;
INSERT INTO `flotilla_tipos_documento` VALUES (1,'Tarjeta de circulación',1,0,30,1),(2,'Seguro vehicular',1,0,45,1),(3,'Verificación vehicular',1,0,45,1),(4,'Permiso de transporte',1,0,60,1),(5,'Tenencia / Refrendo',1,0,30,1),(6,'Licencia de conducir',0,1,60,1),(7,'Examen médico conductor',0,1,30,1),(8,'Otro documento',1,1,30,1);
/*!40000 ALTER TABLE `flotilla_tipos_documento` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_tipos_vehiculo`
--

DROP TABLE IF EXISTS `flotilla_tipos_vehiculo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_tipos_vehiculo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(80) NOT NULL COMMENT 'Camioneta, Camión frigorífico, Pipa, etc.',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo de tipos de vehículo';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_tipos_vehiculo`
--

LOCK TABLES `flotilla_tipos_vehiculo` WRITE;
/*!40000 ALTER TABLE `flotilla_tipos_vehiculo` DISABLE KEYS */;
INSERT INTO `flotilla_tipos_vehiculo` VALUES (1,'Camioneta',1,'2026-06-16 21:57:20'),(2,'Camión de carga',1,'2026-06-16 21:57:20'),(3,'Camión frigorífico',1,'2026-06-16 21:57:20'),(4,'Camión repartidor',1,'2026-06-16 21:57:20'),(5,'Vehículo de servicio',1,'2026-06-16 21:57:20'),(6,'Motocicleta',1,'2026-06-16 21:57:20');
/*!40000 ALTER TABLE `flotilla_tipos_vehiculo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_vehiculos`
--

DROP TABLE IF EXISTS `flotilla_vehiculos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_vehiculos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_id` int(11) NOT NULL,
  `sucursal_id` int(11) DEFAULT NULL,
  `conductor_asignado_id` int(11) DEFAULT NULL COMMENT 'Conductor fijo asignado (puede cambiar por viaje)',
  `alias` varchar(60) DEFAULT NULL COMMENT 'Nombre operativo: Unidad 01, Frío Norte, etc.',
  `marca` varchar(60) NOT NULL,
  `modelo` varchar(80) NOT NULL,
  `anio` year(4) NOT NULL,
  `color` varchar(40) DEFAULT NULL,
  `placas` varchar(20) NOT NULL,
  `numero_serie` varchar(50) DEFAULT NULL COMMENT 'VIN',
  `numero_motor` varchar(50) DEFAULT NULL,
  `combustible_tipo` enum('gasolina','diesel','gas','electrico','hibrido') NOT NULL DEFAULT 'diesel',
  `capacidad_carga_kg` decimal(10,2) DEFAULT NULL,
  `capacidad_pasajeros` tinyint(4) DEFAULT NULL,
  `tiene_refrigeracion` tinyint(1) NOT NULL DEFAULT 0,
  `temp_min_c` decimal(5,2) DEFAULT NULL,
  `temp_max_c` decimal(5,2) DEFAULT NULL,
  `km_inicial` int(11) NOT NULL DEFAULT 0,
  `km_actual` int(11) NOT NULL DEFAULT 0 COMMENT 'Se actualiza con cada registro de combustible/viaje',
  `es_propio` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0 = rentado',
  `proveedor_renta` varchar(100) DEFAULT NULL,
  `fecha_adquisicion` date DEFAULT NULL,
  `costo_adquisicion` decimal(12,2) DEFAULT NULL,
  `estado` enum('activo','taller','inactivo','baja') NOT NULL DEFAULT 'activo',
  `foto_url` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_placas` (`placas`),
  KEY `tipo_id` (`tipo_id`),
  KEY `conductor_asignado_id` (`conductor_asignado_id`),
  KEY `creado_por` (`creado_por`),
  KEY `idx_estado` (`estado`),
  KEY `idx_sucursal` (`sucursal_id`),
  CONSTRAINT `flotilla_vehiculos_ibfk_1` FOREIGN KEY (`tipo_id`) REFERENCES `flotilla_tipos_vehiculo` (`id`),
  CONSTRAINT `flotilla_vehiculos_ibfk_2` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_vehiculos_ibfk_3` FOREIGN KEY (`conductor_asignado_id`) REFERENCES `flotilla_conductores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_vehiculos_ibfk_4` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo principal de vehículos de la flotilla';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_vehiculos`
--

LOCK TABLES `flotilla_vehiculos` WRITE;
/*!40000 ALTER TABLE `flotilla_vehiculos` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_vehiculos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `flotilla_viajes`
--

DROP TABLE IF EXISTS `flotilla_viajes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `flotilla_viajes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehiculo_id` int(11) NOT NULL,
  `conductor_id` int(11) DEFAULT NULL,
  `sucursal_origen_id` int(11) DEFAULT NULL,
  `sucursal_destino_id` int(11) DEFAULT NULL,
  `destino_descripcion` varchar(200) DEFAULT NULL COMMENT 'Si el destino no es una sucursal registrada',
  `fecha_salida` datetime NOT NULL,
  `fecha_llegada` datetime DEFAULT NULL,
  `km_salida` int(11) NOT NULL,
  `km_llegada` int(11) DEFAULT NULL,
  `km_recorridos` int(11) GENERATED ALWAYS AS (case when `km_llegada` is not null then `km_llegada` - `km_salida` else NULL end) STORED,
  `proposito` varchar(200) DEFAULT NULL COMMENT 'Entrega, recogida, mantenimiento, etc.',
  `carga_descripcion` text DEFAULT NULL,
  `carga_peso_kg` decimal(10,2) DEFAULT NULL,
  `estado` enum('en_ruta','completado','cancelado') NOT NULL DEFAULT 'en_ruta',
  `observaciones` text DEFAULT NULL,
  `creado_por` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `sucursal_origen_id` (`sucursal_origen_id`),
  KEY `sucursal_destino_id` (`sucursal_destino_id`),
  KEY `creado_por` (`creado_por`),
  KEY `idx_vehiculo_fecha` (`vehiculo_id`,`fecha_salida`),
  KEY `idx_conductor` (`conductor_id`),
  KEY `idx_estado` (`estado`),
  CONSTRAINT `flotilla_viajes_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `flotilla_vehiculos` (`id`),
  CONSTRAINT `flotilla_viajes_ibfk_2` FOREIGN KEY (`conductor_id`) REFERENCES `flotilla_conductores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_viajes_ibfk_3` FOREIGN KEY (`sucursal_origen_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_viajes_ibfk_4` FOREIGN KEY (`sucursal_destino_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `flotilla_viajes_ibfk_5` FOREIGN KEY (`creado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de viajes: origen, destino, km, carga, conductor';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `flotilla_viajes`
--

LOCK TABLES `flotilla_viajes` WRITE;
/*!40000 ALTER TABLE `flotilla_viajes` DISABLE KEYS */;
/*!40000 ALTER TABLE `flotilla_viajes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `herramientas`
--

DROP TABLE IF EXISTS `herramientas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `herramientas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL COMMENT 'Código interno único, ej. HER-001',
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `tipo` varchar(80) DEFAULT NULL COMMENT 'Eléctrica, manual, medición, etc.',
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `sucursal_id` int(11) NOT NULL COMMENT 'Sucursal donde se almacena',
  `ubicacion` varchar(150) DEFAULT NULL COMMENT 'ej. Taller, Anaquel B-2',
  `estado` enum('disponible','prestada','en_reparacion','extraviada','baja') NOT NULL DEFAULT 'disponible',
  `prestamo_activo_id` int(11) DEFAULT NULL COMMENT 'ID del préstamo activo si está prestada',
  `fecha_adquisicion` date DEFAULT NULL,
  `costo` decimal(10,2) DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL,
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo` (`codigo`),
  KEY `idx_estado` (`estado`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_tipo` (`tipo`),
  KEY `fk_her_proveedor` (`proveedor_id`),
  KEY `fk_her_creador` (`creado_por_id`),
  KEY `fk_her_prestamo_activo` (`prestamo_activo_id`),
  CONSTRAINT `fk_her_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_her_prestamo_activo` FOREIGN KEY (`prestamo_activo_id`) REFERENCES `herramientas_prestamos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_her_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_her_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=117 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `herramientas`
--

LOCK TABLES `herramientas` WRITE;
/*!40000 ALTER TABLE `herramientas` DISABLE KEYS */;
/*!40000 ALTER TABLE `herramientas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `herramientas_prestamos`
--

DROP TABLE IF EXISTS `herramientas_prestamos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `herramientas_prestamos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `herramienta_id` int(11) NOT NULL,
  `prestada_a_id` int(11) NOT NULL COMMENT 'Usuario al que se le prestó',
  `autorizada_por_id` int(11) NOT NULL COMMENT 'Quien autorizó el préstamo',
  `fecha_salida` timestamp NOT NULL DEFAULT current_timestamp(),
  `fecha_devolucion_esperada` date DEFAULT NULL,
  `fecha_devolucion_real` timestamp NULL DEFAULT NULL,
  `recibida_por_id` int(11) DEFAULT NULL COMMENT 'Quien recibe la devolución',
  `motivo` varchar(255) DEFAULT NULL COMMENT 'Para qué se necesita',
  `incidencia_id` int(11) DEFAULT NULL COMMENT 'Si se presta para una orden específica',
  `estado` enum('activo','devuelta','devuelta_con_dano','extraviada') NOT NULL DEFAULT 'activo',
  `condicion_devolucion` enum('buena','dañada','extraviada','reparada') DEFAULT NULL,
  `notas_salida` text DEFAULT NULL,
  `notas_devolucion` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_herramienta` (`herramienta_id`,`fecha_salida`),
  KEY `idx_usuario` (`prestada_a_id`,`estado`),
  KEY `idx_estado_fecha` (`estado`,`fecha_devolucion_esperada`),
  KEY `idx_incidencia` (`incidencia_id`),
  KEY `fk_pres_autoriza` (`autorizada_por_id`),
  KEY `fk_pres_recibe` (`recibida_por_id`),
  CONSTRAINT `fk_pres_autoriza` FOREIGN KEY (`autorizada_por_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_pres_herramienta` FOREIGN KEY (`herramienta_id`) REFERENCES `herramientas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pres_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pres_recibe` FOREIGN KEY (`recibida_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pres_usuario` FOREIGN KEY (`prestada_a_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `herramientas_prestamos`
--

LOCK TABLES `herramientas_prestamos` WRITE;
/*!40000 ALTER TABLE `herramientas_prestamos` DISABLE KEYS */;
/*!40000 ALTER TABLE `herramientas_prestamos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `importaciones`
--

DROP TABLE IF EXISTS `importaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `importaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('usuarios','equipos','incidencias') NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `total_filas` int(11) NOT NULL DEFAULT 0,
  `exitosos` int(11) NOT NULL DEFAULT 0,
  `fallidos` int(11) NOT NULL DEFAULT 0,
  `errores_json` text DEFAULT NULL COMMENT 'JSON con detalles de errores por fila',
  `realizado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_fecha` (`creado_en`),
  KEY `fk_import_usuario` (`realizado_por_id`),
  CONSTRAINT `fk_import_usuario` FOREIGN KEY (`realizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `importaciones`
--

LOCK TABLES `importaciones` WRITE;
/*!40000 ALTER TABLE `importaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `importaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencia_refacciones`
--

DROP TABLE IF EXISTS `incidencia_refacciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencia_refacciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `refaccion_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) DEFAULT NULL COMMENT 'Costo al momento de usar',
  `costo_total` decimal(12,2) DEFAULT NULL COMMENT 'cantidad * costo_unitario',
  `movimiento_id` int(11) DEFAULT NULL COMMENT 'ID del movimiento de salida generado',
  `componente_id` int(11) DEFAULT NULL COMMENT 'Si reemplazó un componente específico',
  `notas` varchar(500) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_incidencia` (`incidencia_id`),
  KEY `idx_refaccion` (`refaccion_id`),
  KEY `idx_componente` (`componente_id`),
  KEY `fk_incref_movimiento` (`movimiento_id`),
  KEY `fk_incref_usuario` (`usuario_id`),
  CONSTRAINT `fk_incref_componente` FOREIGN KEY (`componente_id`) REFERENCES `equipo_componentes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_incref_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_incref_movimiento` FOREIGN KEY (`movimiento_id`) REFERENCES `refacciones_movimientos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_incref_refaccion` FOREIGN KEY (`refaccion_id`) REFERENCES `refacciones` (`id`),
  CONSTRAINT `fk_incref_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencia_refacciones`
--

LOCK TABLES `incidencia_refacciones` WRITE;
/*!40000 ALTER TABLE `incidencia_refacciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `incidencia_refacciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias`
--

DROP TABLE IF EXISTS `incidencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `folio` varchar(30) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `descripcion` text NOT NULL,
  `sucursal_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `tipo_trabajo_id` int(11) DEFAULT NULL,
  `severidad_id` int(11) NOT NULL,
  `estado_id` int(11) NOT NULL,
  `origen_reporte_id` int(11) DEFAULT NULL,
  `equipo_id` int(11) DEFAULT NULL,
  `reportado_por_id` int(11) NOT NULL,
  `reportante_nombre` varchar(150) DEFAULT NULL,
  `reportante_puesto` varchar(100) DEFAULT NULL,
  `asignado_a_id` int(11) DEFAULT NULL,
  `proveedor_escalado_id` int(11) DEFAULT NULL,
  `proveedor_externo_info` varchar(300) DEFAULT NULL COMMENT 'Nombre/datos de proveedor externo no registrado en catálogo',
  `costo_mano_obra` decimal(10,2) DEFAULT NULL COMMENT 'Costo del servicio/mano de obra del proveedor externo',
  `costo_materiales_proveedor` decimal(10,2) DEFAULT NULL COMMENT 'Costo de piezas/materiales aportados por el proveedor externo',
  `costo_materiales_comprados` decimal(10,2) DEFAULT NULL COMMENT 'Gasto en material comprado ad-hoc, fuera de almacén (aplica aunque sea interna)',
  `costo_notas` varchar(300) DEFAULT NULL COMMENT 'Notas sobre los costos (factura, garantía, IVA, etc.)',
  `horas_trabajadas` decimal(6,2) DEFAULT NULL COMMENT 'Horas activas de mano de obra interna (separado del tiempo de resolución)',
  `tarifa_hora_aplicada` decimal(10,2) DEFAULT NULL COMMENT 'Tarifa/hora del técnico congelada al registrar las horas (no cambia si luego sube el salario)',
  `resuelto_por_id` int(11) DEFAULT NULL,
  `causa_raiz` text DEFAULT NULL,
  `solucion` text DEFAULT NULL,
  `recomendaciones` text DEFAULT NULL,
  `acciones_preventivas` text DEFAULT NULL,
  `es_reincidencia` tinyint(1) NOT NULL DEFAULT 0,
  `incidencia_padre_id` int(11) DEFAULT NULL,
  `veces_recurrida` int(11) NOT NULL DEFAULT 0,
  `fecha_evento` datetime NOT NULL,
  `fecha_atencion` datetime DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL,
  `fecha_cierre` datetime DEFAULT NULL,
  `tiempo_respuesta_min` int(11) DEFAULT NULL,
  `tiempo_resolucion_min` int(11) DEFAULT NULL,
  `sla_cumplido` tinyint(1) DEFAULT NULL,
  `fecha_limite_sla` datetime DEFAULT NULL,
  `confirmado_por_reportante` tinyint(1) DEFAULT 0,
  `fecha_confirmacion` datetime DEFAULT NULL,
  `calificacion_servicio` int(11) DEFAULT NULL,
  `comentario_reportante` text DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  `creado_por_id` int(11) NOT NULL,
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `actualizado_por_id` int(11) DEFAULT NULL,
  `archivada` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 si está archivada (resuelta hace >1 año)',
  `fecha_archivado` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `folio` (`folio`),
  KEY `categoria_id` (`categoria_id`),
  KEY `subcategoria_id` (`subcategoria_id`),
  KEY `tipo_trabajo_id` (`tipo_trabajo_id`),
  KEY `estado_id` (`estado_id`),
  KEY `origen_reporte_id` (`origen_reporte_id`),
  KEY `resuelto_por_id` (`resuelto_por_id`),
  KEY `incidencia_padre_id` (`incidencia_padre_id`),
  KEY `creado_por_id` (`creado_por_id`),
  KEY `actualizado_por_id` (`actualizado_por_id`),
  KEY `idx_folio` (`folio`),
  KEY `idx_sucursal_estado` (`sucursal_id`,`estado_id`),
  KEY `idx_area` (`area_id`),
  KEY `idx_severidad` (`severidad_id`),
  KEY `idx_asignado` (`asignado_a_id`),
  KEY `idx_reportado_por` (`reportado_por_id`),
  KEY `idx_equipo` (`equipo_id`),
  KEY `idx_fecha_evento` (`fecha_evento`),
  KEY `idx_reincidencia` (`es_reincidencia`,`incidencia_padre_id`),
  KEY `idx_busqueda_reincidencia` (`equipo_id`,`categoria_id`,`fecha_evento`),
  KEY `fk_incidencia_proveedor` (`proveedor_escalado_id`),
  KEY `idx_archivada` (`archivada`),
  KEY `idx_costos_proveedor` (`proveedor_escalado_id`,`fecha_evento`),
  CONSTRAINT `fk_incidencia_proveedor` FOREIGN KEY (`proveedor_escalado_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_1` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `incidencias_ibfk_10` FOREIGN KEY (`reportado_por_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `incidencias_ibfk_11` FOREIGN KEY (`asignado_a_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_12` FOREIGN KEY (`resuelto_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_13` FOREIGN KEY (`incidencia_padre_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_14` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `incidencias_ibfk_15` FOREIGN KEY (`actualizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_2` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`),
  CONSTRAINT `incidencias_ibfk_3` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_4` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_5` FOREIGN KEY (`tipo_trabajo_id`) REFERENCES `tipos_trabajo` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_6` FOREIGN KEY (`severidad_id`) REFERENCES `severidades` (`id`),
  CONSTRAINT `incidencias_ibfk_7` FOREIGN KEY (`estado_id`) REFERENCES `estados` (`id`),
  CONSTRAINT `incidencias_ibfk_8` FOREIGN KEY (`origen_reporte_id`) REFERENCES `origenes_reporte` (`id`) ON DELETE SET NULL,
  CONSTRAINT `incidencias_ibfk_9` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias`
--

LOCK TABLES `incidencias` WRITE;
/*!40000 ALTER TABLE `incidencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `incidencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias_adjuntos`
--

DROP TABLE IF EXISTS `incidencias_adjuntos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias_adjuntos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `nombre_original` varchar(255) NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta` varchar(500) NOT NULL,
  `tipo_mime` varchar(100) DEFAULT NULL,
  `tamano_bytes` int(11) DEFAULT NULL,
  `momento` varchar(20) DEFAULT 'durante',
  `descripcion` varchar(255) DEFAULT NULL,
  `subido_por_id` int(11) NOT NULL,
  `subido_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `subido_por_id` (`subido_por_id`),
  KEY `idx_incidencia` (`incidencia_id`),
  CONSTRAINT `incidencias_adjuntos_ibfk_1` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidencias_adjuntos_ibfk_2` FOREIGN KEY (`subido_por_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias_adjuntos`
--

LOCK TABLES `incidencias_adjuntos` WRITE;
/*!40000 ALTER TABLE `incidencias_adjuntos` DISABLE KEYS */;
/*!40000 ALTER TABLE `incidencias_adjuntos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias_comentarios`
--

DROP TABLE IF EXISTS `incidencias_comentarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias_comentarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `comentario` text NOT NULL,
  `es_interno` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_incidencia` (`incidencia_id`),
  CONSTRAINT `incidencias_comentarios_ibfk_1` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidencias_comentarios_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias_comentarios`
--

LOCK TABLES `incidencias_comentarios` WRITE;
/*!40000 ALTER TABLE `incidencias_comentarios` DISABLE KEYS */;
/*!40000 ALTER TABLE `incidencias_comentarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias_etiquetas`
--

DROP TABLE IF EXISTS `incidencias_etiquetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias_etiquetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `etiqueta` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_incidencia_etiqueta` (`incidencia_id`,`etiqueta`),
  KEY `idx_etiqueta` (`etiqueta`),
  CONSTRAINT `incidencias_etiquetas_ibfk_1` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias_etiquetas`
--

LOCK TABLES `incidencias_etiquetas` WRITE;
/*!40000 ALTER TABLE `incidencias_etiquetas` DISABLE KEYS */;
/*!40000 ALTER TABLE `incidencias_etiquetas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `incidencias_historial`
--

DROP TABLE IF EXISTS `incidencias_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `incidencias_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `incidencia_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `accion` varchar(50) NOT NULL,
  `campo` varchar(100) DEFAULT NULL,
  `valor_anterior` text DEFAULT NULL,
  `valor_nuevo` text DEFAULT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`),
  KEY `idx_incidencia` (`incidencia_id`),
  KEY `idx_fecha` (`creado_en`),
  CONSTRAINT `incidencias_historial_ibfk_1` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidencias_historial_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `incidencias_historial`
--

LOCK TABLES `incidencias_historial` WRITE;
/*!40000 ALTER TABLE `incidencias_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `incidencias_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `mantenimientos`
--

DROP TABLE IF EXISTS `mantenimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `mantenimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipo_id` int(11) NOT NULL,
  `titulo` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_programada` date NOT NULL,
  `hora_programada` time DEFAULT NULL,
  `asignado_a_id` int(11) DEFAULT NULL COMMENT 'Tecnico asignado',
  `proveedor_id` int(11) DEFAULT NULL COMMENT 'Si lo hace un proveedor externo',
  `estado` enum('programado','proximo','en_progreso','completado','cancelado','vencido') NOT NULL DEFAULT 'programado',
  `es_recurrente` tinyint(1) NOT NULL DEFAULT 0,
  `recurrencia_tipo` enum('dias','semanas','meses','anios') DEFAULT NULL,
  `recurrencia_valor` int(11) DEFAULT NULL COMMENT 'Cada cuantas unidades (ej. 3 meses)',
  `mantenimiento_padre_id` int(11) DEFAULT NULL COMMENT 'Si fue auto-generado, apunta al original',
  `fecha_inicio_real` datetime DEFAULT NULL,
  `fecha_completado` datetime DEFAULT NULL,
  `realizado_por_id` int(11) DEFAULT NULL COMMENT 'Quien lo ejecuto realmente',
  `resultado` text DEFAULT NULL COMMENT 'Notas de lo que se hizo',
  `costo` decimal(10,2) DEFAULT NULL,
  `incidencia_generada_id` int(11) DEFAULT NULL COMMENT 'Si se convirtio en incidencia',
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_equipo` (`equipo_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_fecha` (`fecha_programada`),
  KEY `idx_asignado` (`asignado_a_id`),
  KEY `idx_padre` (`mantenimiento_padre_id`),
  KEY `fk_mant_proveedor` (`proveedor_id`),
  KEY `fk_mant_realizado` (`realizado_por_id`),
  KEY `fk_mant_creador` (`creado_por_id`),
  KEY `fk_mant_incidencia` (`incidencia_generada_id`),
  CONSTRAINT `fk_mant_asignado` FOREIGN KEY (`asignado_a_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mant_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mant_equipo` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mant_incidencia` FOREIGN KEY (`incidencia_generada_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mant_padre` FOREIGN KEY (`mantenimiento_padre_id`) REFERENCES `mantenimientos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mant_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mant_realizado` FOREIGN KEY (`realizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `mantenimientos`
--

LOCK TABLES `mantenimientos` WRITE;
/*!40000 ALTER TABLE `mantenimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `mantenimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medidor_lecturas`
--

DROP TABLE IF EXISTS `medidor_lecturas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medidor_lecturas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `medidor_id` int(11) NOT NULL,
  `fecha_lectura` date NOT NULL,
  `valor_lectura` decimal(14,3) NOT NULL COMMENT 'numero acumulado que marca el medidor',
  `consumo` decimal(14,3) DEFAULT NULL COMMENT 'calculado: valor - lectura anterior',
  `tarifa_aplicada` decimal(12,4) DEFAULT NULL COMMENT 'tarifa congelada al registrar',
  `costo` decimal(14,2) DEFAULT NULL COMMENT 'consumo x tarifa_aplicada (congelado)',
  `es_reinicio` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = el medidor se reinicio/reemplazo (no calcular consumo)',
  `leido_por_id` int(11) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL COMMENT 'ruta de evidencia (opcional)',
  `nota` varchar(300) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_lectura_medidor_fecha` (`medidor_id`,`fecha_lectura`),
  KEY `idx_lectura_fecha` (`fecha_lectura`),
  KEY `fk_lectura_leido_por` (`leido_por_id`),
  CONSTRAINT `fk_lectura_leido_por` FOREIGN KEY (`leido_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lectura_medidor` FOREIGN KEY (`medidor_id`) REFERENCES `medidores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medidor_lecturas`
--

LOCK TABLES `medidor_lecturas` WRITE;
/*!40000 ALTER TABLE `medidor_lecturas` DISABLE KEYS */;
/*!40000 ALTER TABLE `medidor_lecturas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medidor_tipos`
--

DROP TABLE IF EXISTS `medidor_tipos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medidor_tipos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `unidad` varchar(20) NOT NULL COMMENT 'kWh, m3, L, etc.',
  `icono` varchar(50) DEFAULT NULL COMMENT 'nombre de icono lucide',
  `color` varchar(7) DEFAULT NULL COMMENT 'color hex para la UI',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medidor_tipos`
--

LOCK TABLES `medidor_tipos` WRITE;
/*!40000 ALTER TABLE `medidor_tipos` DISABLE KEYS */;
INSERT INTO `medidor_tipos` VALUES (1,'Luz','kWh','zap','#F59E0B',1,'2026-06-07 10:25:56'),(2,'Agua','m3','droplet','#0EA5E9',1,'2026-06-07 10:25:56'),(3,'Gas','m3','flame','#EF4444',1,'2026-06-07 10:25:56'),(4,'Diésel','L','fuel','#6B7280',1,'2026-06-07 10:25:56'),(5,'Aire comprimido','m3','wind','#14B8A6',1,'2026-06-07 10:25:56');
/*!40000 ALTER TABLE `medidor_tipos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `medidores`
--

DROP TABLE IF EXISTS `medidores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `medidores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL COMMENT 'etiqueta, ej. Medidor principal CFE',
  `numero_serie` varchar(100) DEFAULT NULL,
  `sucursal_id` int(11) NOT NULL,
  `area_id` int(11) DEFAULT NULL,
  `ubicacion` varchar(255) DEFAULT NULL COMMENT 'texto libre, ej. Patio trasero',
  `tarifa` decimal(12,4) DEFAULT NULL COMMENT 'precio por unidad (estimacion de costo)',
  `valor_inicial` decimal(14,3) DEFAULT NULL COMMENT 'lectura base al dar de alta',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `notas` text DEFAULT NULL,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_medidor_tipo` (`tipo_id`),
  KEY `idx_medidor_sucursal` (`sucursal_id`),
  KEY `idx_medidor_area` (`area_id`),
  KEY `fk_medidor_creado_por` (`creado_por_id`),
  CONSTRAINT `fk_medidor_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_medidor_creado_por` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_medidor_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`),
  CONSTRAINT `fk_medidor_tipo` FOREIGN KEY (`tipo_id`) REFERENCES `medidor_tipos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `medidores`
--

LOCK TABLES `medidores` WRITE;
/*!40000 ALTER TABLE `medidores` DISABLE KEYS */;
/*!40000 ALTER TABLE `medidores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificacion_envios`
--

DROP TABLE IF EXISTS `notificacion_envios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificacion_envios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `notificacion_id` int(11) DEFAULT NULL COMMENT 'ID en tabla notificaciones (si existe)',
  `usuario_id` int(11) NOT NULL,
  `canal` enum('email','telegram') NOT NULL,
  `tipo` varchar(60) DEFAULT NULL,
  `asunto` varchar(255) DEFAULT NULL,
  `estado` enum('ok','error') NOT NULL DEFAULT 'ok',
  `error_detalle` text DEFAULT NULL,
  `enviado_en` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario_canal` (`usuario_id`,`canal`),
  KEY `idx_enviado_en` (`enviado_en`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificacion_envios`
--

LOCK TABLES `notificacion_envios` WRITE;
/*!40000 ALTER TABLE `notificacion_envios` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificacion_envios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificacion_preferencias`
--

DROP TABLE IF EXISTS `notificacion_preferencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificacion_preferencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(60) NOT NULL COMMENT 'Tipo de notificación (NOTIF_TIPOS)',
  `canal_inapp` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Notificación in-app activada',
  `canal_email` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Notificación por email activada',
  `canal_telegram` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Notificación por Telegram activada',
  `creado_en` datetime NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_tipo` (`usuario_id`,`tipo`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificacion_preferencias`
--

LOCK TABLES `notificacion_preferencias` WRITE;
/*!40000 ALTER TABLE `notificacion_preferencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificacion_preferencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `notificaciones`
--

DROP TABLE IF EXISTS `notificaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `titulo` varchar(255) NOT NULL,
  `mensaje` text DEFAULT NULL,
  `enlace` varchar(500) DEFAULT NULL,
  `leida` tinyint(1) NOT NULL DEFAULT 0,
  `leida_en` datetime DEFAULT NULL,
  `creada_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_usuario_leida` (`usuario_id`,`leida`),
  KEY `idx_fecha` (`creada_en`),
  CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `notificaciones`
--

LOCK TABLES `notificaciones` WRITE;
/*!40000 ALTER TABLE `notificaciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `notificaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `origenes_reporte`
--

DROP TABLE IF EXISTS `origenes_reporte`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `origenes_reporte` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `origenes_reporte`
--

LOCK TABLES `origenes_reporte` WRITE;
/*!40000 ALTER TABLE `origenes_reporte` DISABLE KEYS */;
INSERT INTO `origenes_reporte` VALUES (8,'Reporte de operador',1),(9,'Inspección de rutina',1),(10,'Mantenimiento programado',1),(11,'Falla detectada en línea',1),(12,'Auditoría',1),(13,'Solicitud de supervisor',1),(14,'Alarma de sistema',1),(15,'Otro',1),(16,'Seguridad e Higiene',1);
/*!40000 ALTER TABLE `origenes_reporte` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `plantillas_incidencias`
--

DROP TABLE IF EXISTS `plantillas_incidencias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plantillas_incidencias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL COMMENT 'Para mostrar en la lista de plantillas',
  `icono` varchar(50) DEFAULT 'file-text' COMMENT 'Nombre del icono Lucide',
  `color` varchar(7) DEFAULT '#6B7280',
  `titulo` varchar(255) DEFAULT NULL,
  `descripcion_inc` text DEFAULT NULL COMMENT 'Descripcion del problema pre-rellenada',
  `area_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `subcategoria_id` int(11) DEFAULT NULL,
  `tipo_trabajo_id` int(11) DEFAULT NULL,
  `severidad_id` int(11) DEFAULT NULL,
  `origen_reporte_id` int(11) DEFAULT NULL,
  `solucion_sugerida` text DEFAULT NULL COMMENT 'Solucion tipica para este problema',
  `usos` int(11) NOT NULL DEFAULT 0 COMMENT 'Veces que se ha usado esta plantilla',
  `creado_por_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`activo`),
  KEY `idx_usos` (`usos`),
  KEY `fk_plantilla_area` (`area_id`),
  KEY `fk_plantilla_categoria` (`categoria_id`),
  KEY `fk_plantilla_subcategoria` (`subcategoria_id`),
  KEY `fk_plantilla_tipo` (`tipo_trabajo_id`),
  KEY `fk_plantilla_severidad` (`severidad_id`),
  KEY `fk_plantilla_origen` (`origen_reporte_id`),
  KEY `fk_plantilla_creador` (`creado_por_id`),
  CONSTRAINT `fk_plantilla_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_origen` FOREIGN KEY (`origen_reporte_id`) REFERENCES `origenes_reporte` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_severidad` FOREIGN KEY (`severidad_id`) REFERENCES `severidades` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_subcategoria` FOREIGN KEY (`subcategoria_id`) REFERENCES `subcategorias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_plantilla_tipo` FOREIGN KEY (`tipo_trabajo_id`) REFERENCES `tipos_trabajo` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `plantillas_incidencias`
--

LOCK TABLES `plantillas_incidencias` WRITE;
/*!40000 ALTER TABLE `plantillas_incidencias` DISABLE KEYS */;
/*!40000 ALTER TABLE `plantillas_incidencias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedor_contactos`
--

DROP TABLE IF EXISTS `proveedor_contactos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedor_contactos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `nombre` varchar(150) NOT NULL COMMENT 'Nombre de la persona contacto',
  `puesto` varchar(100) DEFAULT NULL COMMENT 'ej. Asesor de basculas, Soporte',
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL COMMENT 'ej. Solo turno matutino',
  `es_principal` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Marca el contacto principal',
  `orden` int(11) NOT NULL DEFAULT 0,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_contacto_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedor_contactos`
--

LOCK TABLES `proveedor_contactos` WRITE;
/*!40000 ALTER TABLE `proveedor_contactos` DISABLE KEYS */;
/*!40000 ALTER TABLE `proveedor_contactos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedor_marcas`
--

DROP TABLE IF EXISTS `proveedor_marcas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedor_marcas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `marca` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_proveedor_marca` (`proveedor_id`,`marca`),
  KEY `idx_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_marca_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedor_marcas`
--

LOCK TABLES `proveedor_marcas` WRITE;
/*!40000 ALTER TABLE `proveedor_marcas` DISABLE KEYS */;
/*!40000 ALTER TABLE `proveedor_marcas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedor_sucursales`
--

DROP TABLE IF EXISTS `proveedor_sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedor_sucursales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prov_suc` (`proveedor_id`,`sucursal_id`),
  KEY `idx_ps_prov` (`proveedor_id`),
  KEY `idx_ps_suc` (`sucursal_id`),
  CONSTRAINT `fk_ps_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ps_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedor_sucursales`
--

LOCK TABLES `proveedor_sucursales` WRITE;
/*!40000 ALTER TABLE `proveedor_sucursales` DISABLE KEYS */;
/*!40000 ALTER TABLE `proveedor_sucursales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedor_tipos_equipo`
--

DROP TABLE IF EXISTS `proveedor_tipos_equipo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedor_tipos_equipo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_proveedor_tipo` (`proveedor_id`,`tipo`),
  KEY `idx_proveedor` (`proveedor_id`),
  CONSTRAINT `fk_tipo_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=21 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedor_tipos_equipo`
--

LOCK TABLES `proveedor_tipos_equipo` WRITE;
/*!40000 ALTER TABLE `proveedor_tipos_equipo` DISABLE KEYS */;
/*!40000 ALTER TABLE `proveedor_tipos_equipo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL COMMENT 'Nombre comercial',
  `razon_social` varchar(200) DEFAULT NULL,
  `rfc` varchar(20) DEFAULT NULL,
  `servicio` varchar(255) DEFAULT NULL COMMENT 'Descripcion corta del servicio que ofrece',
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `sitio_web` varchar(200) DEFAULT NULL,
  `horario_atencion` varchar(255) DEFAULT NULL COMMENT 'ej. Lun-Vie 9-18hr',
  `calificacion` tinyint(3) unsigned DEFAULT NULL COMMENT '1-5 estrellas',
  `notas` text DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nombre` (`nombre`),
  KEY `idx_activo` (`activo`),
  KEY `fk_proveedor_creador` (`creado_por_id`),
  CONSTRAINT `fk_proveedor_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=95 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recordatorios`
--

DROP TABLE IF EXISTS `recordatorios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recordatorios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL COMMENT 'A quién se le envía',
  `titulo` varchar(200) NOT NULL,
  `mensaje` varchar(500) DEFAULT NULL,
  `fecha_envio` datetime NOT NULL COMMENT 'Cuándo enviar',
  `enlace` varchar(255) DEFAULT NULL,
  `entidad` varchar(50) DEFAULT NULL,
  `entidad_id` int(11) DEFAULT NULL,
  `enviado` tinyint(1) NOT NULL DEFAULT 0,
  `enviado_en` timestamp NULL DEFAULT NULL,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_enviar` (`enviado`,`fecha_envio`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `fk_rec_creador` (`creado_por_id`),
  CONSTRAINT `fk_rec_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rec_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recordatorios`
--

LOCK TABLES `recordatorios` WRITE;
/*!40000 ALTER TABLE `recordatorios` DISABLE KEYS */;
/*!40000 ALTER TABLE `recordatorios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refacciones`
--

DROP TABLE IF EXISTS `refacciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refacciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(50) NOT NULL COMMENT 'Código interno único',
  `nombre` varchar(200) NOT NULL,
  `descripcion` text DEFAULT NULL,
  `marca` varchar(100) DEFAULT NULL,
  `modelo` varchar(100) DEFAULT NULL,
  `numero_parte` varchar(100) DEFAULT NULL COMMENT 'Part number del fabricante',
  `categoria` varchar(80) DEFAULT NULL COMMENT 'Mecánica, Eléctrica, etc.',
  `unidad_medida` varchar(20) DEFAULT 'pieza' COMMENT 'pieza, metro, kg, litro',
  `costo_unitario` decimal(10,2) DEFAULT NULL,
  `proveedor_id` int(11) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_id` int(11) DEFAULT NULL,
  `actualizado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo` (`codigo`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_categoria` (`categoria`),
  KEY `idx_proveedor` (`proveedor_id`),
  KEY `fk_ref_creador` (`creado_por_id`),
  KEY `fk_ref_actualizador` (`actualizado_por_id`),
  CONSTRAINT `fk_ref_actualizador` FOREIGN KEY (`actualizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ref_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ref_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=792 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refacciones`
--

LOCK TABLES `refacciones` WRITE;
/*!40000 ALTER TABLE `refacciones` DISABLE KEYS */;
/*!40000 ALTER TABLE `refacciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refacciones_compatibles`
--

DROP TABLE IF EXISTS `refacciones_compatibles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refacciones_compatibles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `refaccion_id` int(11) NOT NULL,
  `equipo_id` int(11) DEFAULT NULL,
  `componente_id` int(11) DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL COMMENT 'ej. Para etapa 2, lado izquierdo',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ref_equipo` (`refaccion_id`,`equipo_id`),
  UNIQUE KEY `uk_ref_componente` (`refaccion_id`,`componente_id`),
  KEY `idx_equipo` (`equipo_id`),
  KEY `idx_componente` (`componente_id`),
  CONSTRAINT `fk_comp_componente_compat` FOREIGN KEY (`componente_id`) REFERENCES `equipo_componentes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comp_equipo_compat` FOREIGN KEY (`equipo_id`) REFERENCES `equipos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comp_refaccion` FOREIGN KEY (`refaccion_id`) REFERENCES `refacciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refacciones_compatibles`
--

LOCK TABLES `refacciones_compatibles` WRITE;
/*!40000 ALTER TABLE `refacciones_compatibles` DISABLE KEYS */;
/*!40000 ALTER TABLE `refacciones_compatibles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refacciones_movimientos`
--

DROP TABLE IF EXISTS `refacciones_movimientos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refacciones_movimientos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `refaccion_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL,
  `tipo` enum('entrada','salida','ajuste','transferencia') NOT NULL,
  `cantidad` decimal(10,2) NOT NULL COMMENT 'Positivo siempre, el tipo define el signo',
  `cantidad_antes` decimal(10,2) NOT NULL,
  `cantidad_despues` decimal(10,2) NOT NULL,
  `motivo` varchar(80) DEFAULT NULL COMMENT 'compra, devolucion, uso_mantenimiento, ajuste_inventario, merma',
  `notas` text DEFAULT NULL,
  `incidencia_id` int(11) DEFAULT NULL COMMENT 'Si el movimiento es por una orden de trabajo',
  `componente_id` int(11) DEFAULT NULL COMMENT 'Si reemplaza un componente específico',
  `sucursal_destino_id` int(11) DEFAULT NULL,
  `costo_unitario` decimal(10,2) DEFAULT NULL,
  `usuario_id` int(11) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_refaccion` (`refaccion_id`,`creado_en`),
  KEY `idx_sucursal` (`sucursal_id`,`creado_en`),
  KEY `idx_incidencia` (`incidencia_id`),
  KEY `idx_tipo` (`tipo`,`creado_en`),
  KEY `fk_mov_suc_destino` (`sucursal_destino_id`),
  KEY `fk_mov_componente` (`componente_id`),
  KEY `fk_mov_usuario` (`usuario_id`),
  CONSTRAINT `fk_mov_componente` FOREIGN KEY (`componente_id`) REFERENCES `equipo_componentes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mov_incidencia` FOREIGN KEY (`incidencia_id`) REFERENCES `incidencias` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mov_refaccion` FOREIGN KEY (`refaccion_id`) REFERENCES `refacciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mov_suc_destino` FOREIGN KEY (`sucursal_destino_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_mov_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mov_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refacciones_movimientos`
--

LOCK TABLES `refacciones_movimientos` WRITE;
/*!40000 ALTER TABLE `refacciones_movimientos` DISABLE KEYS */;
/*!40000 ALTER TABLE `refacciones_movimientos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `refacciones_stock`
--

DROP TABLE IF EXISTS `refacciones_stock`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refacciones_stock` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `refaccion_id` int(11) NOT NULL,
  `sucursal_id` int(11) NOT NULL,
  `cantidad_actual` decimal(10,2) NOT NULL DEFAULT 0.00,
  `cantidad_minima` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Alerta cuando stock <= mínimo',
  `cantidad_optima` decimal(10,2) DEFAULT NULL COMMENT 'Cantidad ideal sugerida',
  `ubicacion` varchar(150) DEFAULT NULL COMMENT 'ej. Anaquel A-3, Pasillo 2, Caja 14',
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_refaccion_sucursal` (`refaccion_id`,`sucursal_id`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_stock_bajo` (`sucursal_id`,`cantidad_actual`),
  CONSTRAINT `fk_stock_refaccion` FOREIGN KEY (`refaccion_id`) REFERENCES `refacciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_stock_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `refacciones_stock`
--

LOCK TABLES `refacciones_stock` WRITE;
/*!40000 ALTER TABLE `refacciones_stock` DISABLE KEYS */;
/*!40000 ALTER TABLE `refacciones_stock` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `reglas_asignacion`
--

DROP TABLE IF EXISTS `reglas_asignacion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `reglas_asignacion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL COMMENT 'Nombre descriptivo de la regla',
  `descripcion` varchar(255) DEFAULT NULL,
  `sucursal_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `categoria_id` int(11) DEFAULT NULL,
  `tipo_trabajo_id` int(11) DEFAULT NULL,
  `severidad_id` int(11) DEFAULT NULL,
  `asignar_a_id` int(11) NOT NULL,
  `prioridad` int(11) NOT NULL DEFAULT 100 COMMENT 'Menor = se evalúa antes',
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `veces_aplicada` int(11) NOT NULL DEFAULT 0,
  `creado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_activa_prioridad` (`activa`,`prioridad`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_area` (`area_id`),
  KEY `fk_regla_categoria` (`categoria_id`),
  KEY `fk_regla_tipo` (`tipo_trabajo_id`),
  KEY `fk_regla_severidad` (`severidad_id`),
  KEY `fk_regla_asignar` (`asignar_a_id`),
  KEY `fk_regla_creador` (`creado_por_id`),
  CONSTRAINT `fk_regla_area` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regla_asignar` FOREIGN KEY (`asignar_a_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regla_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regla_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_regla_severidad` FOREIGN KEY (`severidad_id`) REFERENCES `severidades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regla_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_regla_tipo` FOREIGN KEY (`tipo_trabajo_id`) REFERENCES `tipos_trabajo` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `reglas_asignacion`
--

LOCK TABLES `reglas_asignacion` WRITE;
/*!40000 ALTER TABLE `reglas_asignacion` DISABLE KEYS */;
/*!40000 ALTER TABLE `reglas_asignacion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `puede_administrar` tinyint(1) NOT NULL DEFAULT 0,
  `puede_ver_todas_sucursales` tinyint(1) NOT NULL DEFAULT 0,
  `puede_resolver` tinyint(1) NOT NULL DEFAULT 0,
  `puede_crear_solicitud` tinyint(1) NOT NULL DEFAULT 1,
  `puede_ver_reportes` tinyint(1) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'Administrador','Control total del sistema, configura todo',1,1,1,1,1,1,'2026-01-01 00:00:00'),(2,'Técnico de Mantenimiento','Atiende y resuelve órdenes de trabajo en todas las plantas',0,1,1,1,1,1,'2026-01-01 00:00:00'),(3,'Supervisor de Planta','Supervisa su planta y genera reportes',0,0,0,1,1,1,'2026-01-01 00:00:00'),(4,'Operador / Reportante','Reporta fallas y da seguimiento a las órdenes de su área',0,0,0,1,0,1,'2026-01-01 00:00:00'),(5,'Solo Lectura','Consulta y filtra sin modificar',0,1,0,0,1,1,'2026-01-01 00:00:00'),(7,'Arquitecto','Arquitecto de planta con permisos completos: gestiona catálogos, equipos, refacciones, herramientas, proveedores y atiende órdenes en todas las plantas',1,1,1,1,1,1,'2026-05-29 11:21:49');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sesiones`
--

DROP TABLE IF EXISTS `sesiones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sesiones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `session_id` varchar(128) NOT NULL COMMENT 'PHP session_id()',
  `ip` varchar(45) DEFAULT NULL COMMENT 'IPv4 o IPv6',
  `user_agent` varchar(500) DEFAULT NULL,
  `dispositivo` varchar(100) DEFAULT NULL COMMENT 'Dispositivo detectado (Windows, Mac, Android, iPhone, etc)',
  `navegador` varchar(50) DEFAULT NULL COMMENT 'Navegador detectado',
  `activa` tinyint(1) NOT NULL DEFAULT 1,
  `motivo_cierre` varchar(100) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `ultima_actividad` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `cerrada_en` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_id` (`session_id`),
  KEY `idx_usuario_activa` (`usuario_id`,`activa`),
  KEY `idx_creado` (`creado_en`),
  CONSTRAINT `fk_sesion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=81 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sesiones`
--

LOCK TABLES `sesiones` WRITE;
/*!40000 ALTER TABLE `sesiones` DISABLE KEYS */;
/*!40000 ALTER TABLE `sesiones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `severidades`
--

DROP TABLE IF EXISTS `severidades`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `severidades` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `nivel` int(11) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT '#6B7280',
  `sla_horas` int(11) DEFAULT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`),
  UNIQUE KEY `nivel` (`nivel`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `severidades`
--

LOCK TABLES `severidades` WRITE;
/*!40000 ALTER TABLE `severidades` DISABLE KEYS */;
INSERT INTO `severidades` VALUES (1,'Crítica',1,'#DC2626',2,'Línea o equipo crítico detenido, afecta producción',1),(2,'Alta',2,'#EA580C',8,'Afecta producción pero hay alternativas',1),(3,'Media',3,'#D97706',24,'Sin afectación inmediata a producción',1),(4,'Baja',4,'#16A34A',72,'Programable, sin urgencia',1);
/*!40000 ALTER TABLE `severidades` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `subcategorias`
--

DROP TABLE IF EXISTS `subcategorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subcategorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_categoria_nombre` (`categoria_id`,`nombre`),
  CONSTRAINT `subcategorias_ibfk_1` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `subcategorias`
--

LOCK TABLES `subcategorias` WRITE;
/*!40000 ALTER TABLE `subcategorias` DISABLE KEYS */;
/*!40000 ALTER TABLE `subcategorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sucursal_plantas`
--

DROP TABLE IF EXISTS `sucursal_plantas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sucursal_plantas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sucursal_id` int(11) NOT NULL,
  `nombre` varchar(80) NOT NULL COMMENT 'Ej: Planta baja, Piso 1, Bodega',
  `orden` int(11) NOT NULL DEFAULT 0 COMMENT 'Para ordenar las pestañas',
  `plano_url` varchar(255) DEFAULT NULL,
  `plano_subido_en` timestamp NULL DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_sucursal` (`sucursal_id`,`orden`),
  CONSTRAINT `fk_planta_sucursal` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sucursal_plantas`
--

LOCK TABLES `sucursal_plantas` WRITE;
/*!40000 ALTER TABLE `sucursal_plantas` DISABLE KEYS */;
/*!40000 ALTER TABLE `sucursal_plantas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `sucursales`
--

DROP TABLE IF EXISTS `sucursales`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sucursales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) NOT NULL,
  `direccion` varchar(255) DEFAULT NULL,
  `telefono` varchar(50) DEFAULT NULL,
  `responsable` varchar(150) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sucursales`
--

LOCK TABLES `sucursales` WRITE;
/*!40000 ALTER TABLE `sucursales` DISABLE KEYS */;
INSERT INTO `sucursales` VALUES (1,'Benton Tortilleria','BEN',NULL,NULL,NULL,1,'2026-06-23 00:00:00','2026-06-23 00:00:00');
/*!40000 ALTER TABLE `sucursales` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_trabajo`
--

DROP TABLE IF EXISTS `tipos_trabajo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_trabajo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  `color` varchar(20) DEFAULT '#6B7280',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_trabajo`
--

LOCK TABLES `tipos_trabajo` WRITE;
/*!40000 ALTER TABLE `tipos_trabajo` DISABLE KEYS */;
INSERT INTO `tipos_trabajo` VALUES (15,'Correctivo',NULL,'#DC2626',1,'2026-05-28 16:10:57'),(16,'Preventivo',NULL,'#10B981',1,'2026-05-28 16:10:57'),(17,'Predictivo',NULL,'#3B82F6',1,'2026-05-28 16:10:57'),(18,'Calibración',NULL,'#F59E0B',1,'2026-05-28 16:10:57'),(19,'Inspección',NULL,'#06B6D4',1,'2026-05-28 16:10:57'),(20,'Limpieza',NULL,'#84CC16',1,'2026-05-28 16:10:57'),(21,'Lubricación',NULL,'#8B5CF6',1,'2026-05-28 16:10:57'),(22,'Instalación',NULL,'#71717a',1,'2026-05-28 16:10:57'),(23,'Modificación/Mejora',NULL,'#EC4899',1,'2026-05-28 16:10:57'),(24,'Emergencia',NULL,'#991B1B',1,'2026-05-28 16:10:57'),(25,'Obligado',NULL,'#6B7280',1,'2026-06-15 19:06:18');
/*!40000 ALTER TABLE `tipos_trabajo` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `nombre_completo` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `avatar_url` varchar(255) DEFAULT NULL COMMENT 'Ruta relativa de la foto de perfil',
  `pagina_inicio_preferida` varchar(100) DEFAULT 'dashboard.php',
  `telefono` varchar(50) DEFAULT NULL,
  `rol_id` int(11) NOT NULL,
  `sucursal_id` int(11) DEFAULT NULL,
  `area_id` int(11) DEFAULT NULL,
  `puesto` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `ultimo_login` datetime DEFAULT NULL,
  `intentos_fallidos` int(11) NOT NULL DEFAULT 0,
  `bloqueado_hasta` datetime DEFAULT NULL,
  `debe_cambiar_password` tinyint(1) NOT NULL DEFAULT 0,
  `creado_en` datetime DEFAULT current_timestamp(),
  `actualizado_en` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tarifa_hora` decimal(10,2) DEFAULT NULL COMMENT 'Costo por hora del técnico para cálculo de mano de obra interna',
  `preferencias` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Preferencias de UI del usuario en formato JSON' CHECK (json_valid(`preferencias`)),
  `telegram_chat_id` varchar(50) DEFAULT NULL COMMENT 'Chat ID de Telegram del usuario (obtenido via @userinfobot)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuario` (`usuario`),
  KEY `rol_id` (`rol_id`),
  KEY `idx_usuario_activo` (`usuario`,`activo`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_area` (`area_id`),
  CONSTRAINT `usuarios_ibfk_1` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `usuarios_ibfk_2` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usuarios_ibfk_3` FOREIGN KEY (`area_id`) REFERENCES `areas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `usuarios`
--

LOCK TABLES `usuarios` WRITE;
/*!40000 ALTER TABLE `usuarios` DISABLE KEYS */;
INSERT INTO `usuarios` VALUES (1,'Admin','$2y$10$KDo5/PKQ/DUVtIJZ.ouoY.v7E7mT4pQElbR28T0WdqODShiYATOd6','Administrador del Sistema',NULL,NULL,'dashboard.php',NULL,1,1,NULL,'Administrador',NULL,1,NULL,0,NULL,0,'2026-06-23 00:00:00','2026-06-23 00:00:00',NULL,NULL,NULL);
/*!40000 ALTER TABLE `usuarios` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_accesos`
--

DROP TABLE IF EXISTS `vault_accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_accesos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entrada_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `accion` enum('ver_password','copiar_password','ver_entrada') NOT NULL,
  `ip` varchar(45) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entrada` (`entrada_id`,`creado_en`),
  KEY `idx_usuario` (`usuario_id`,`creado_en`),
  CONSTRAINT `fk_acc_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `vault_entradas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acc_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_accesos`
--

LOCK TABLES `vault_accesos` WRITE;
/*!40000 ALTER TABLE `vault_accesos` DISABLE KEYS */;
/*!40000 ALTER TABLE `vault_accesos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_categorias`
--

DROP TABLE IF EXISTS `vault_categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `familia` varchar(60) NOT NULL COMMENT 'Grupo visual: Acceso, Infraestructura, etc.',
  `familia_orden` int(11) NOT NULL DEFAULT 0,
  `nombre` varchar(100) NOT NULL,
  `icono` varchar(50) DEFAULT 'folder',
  `color` varchar(20) DEFAULT '#71717a',
  `orden` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_familia` (`familia`,`orden`),
  KEY `idx_orden` (`orden`)
) ENGINE=InnoDB AUTO_INCREMENT=71 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_categorias`
--

LOCK TABLES `vault_categorias` WRITE;
/*!40000 ALTER TABLE `vault_categorias` DISABLE KEYS */;
/*!40000 ALTER TABLE `vault_categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_entradas`
--

DROP TABLE IF EXISTS `vault_entradas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_entradas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `categoria_id` int(11) NOT NULL,
  `nombre` varchar(200) NOT NULL,
  `url` varchar(500) DEFAULT NULL,
  `usuario` varchar(200) DEFAULT NULL,
  `password_cifrado` text DEFAULT NULL COMMENT 'AES-256 encrypted',
  `notas` text DEFAULT NULL COMMENT 'Markdown libre',
  `archivos` text DEFAULT NULL COMMENT 'Rutas UNC u observaciones, editable',
  `version_build` varchar(100) DEFAULT NULL COMMENT 'Para instaladores/drivers',
  `vencimiento` date DEFAULT NULL COMMENT 'Para licencias/certificados',
  `tags` varchar(500) DEFAULT NULL COMMENT 'Coma-separadas',
  `sucursal_id` int(11) DEFAULT NULL COMMENT 'NULL = todas / N/A',
  `sensibilidad` enum('normal','alta','critica') NOT NULL DEFAULT 'normal',
  `permisos_tipo` enum('todos','rol','sucursal','usuarios','admin') NOT NULL DEFAULT 'admin' COMMENT 'todos=visible para todos / rol=por roles_ids / sucursal=por sucursales_ids / usuarios=lista específica / admin=solo admin',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `creado_por_id` int(11) DEFAULT NULL,
  `actualizado_por_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_sucursal` (`sucursal_id`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_activo` (`activo`),
  KEY `fk_vault_creador` (`creado_por_id`),
  KEY `fk_vault_actualizador` (`actualizado_por_id`),
  CONSTRAINT `fk_vault_actualizador` FOREIGN KEY (`actualizado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vault_cat` FOREIGN KEY (`categoria_id`) REFERENCES `vault_categorias` (`id`),
  CONSTRAINT `fk_vault_creador` FOREIGN KEY (`creado_por_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_vault_suc` FOREIGN KEY (`sucursal_id`) REFERENCES `sucursales` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_entradas`
--

LOCK TABLES `vault_entradas` WRITE;
/*!40000 ALTER TABLE `vault_entradas` DISABLE KEYS */;
/*!40000 ALTER TABLE `vault_entradas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_favoritos`
--

DROP TABLE IF EXISTS `vault_favoritos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_favoritos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entrada_id` int(11) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_entrada_usuario` (`entrada_id`,`usuario_id`),
  KEY `fk_fav_usuario` (`usuario_id`),
  CONSTRAINT `fk_fav_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `vault_entradas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_fav_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_favoritos`
--

LOCK TABLES `vault_favoritos` WRITE;
/*!40000 ALTER TABLE `vault_favoritos` DISABLE KEYS */;
/*!40000 ALTER TABLE `vault_favoritos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_historial`
--

DROP TABLE IF EXISTS `vault_historial`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_historial` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entrada_id` int(11) NOT NULL,
  `usuario_id` int(11) DEFAULT NULL,
  `accion` enum('crear','editar','eliminar','password_cambiada','permisos_cambiados') NOT NULL,
  `descripcion` varchar(500) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_entrada` (`entrada_id`,`creado_en`),
  KEY `fk_hist_usuario` (`usuario_id`),
  CONSTRAINT `fk_hist_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `vault_entradas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hist_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_historial`
--

LOCK TABLES `vault_historial` WRITE;
/*!40000 ALTER TABLE `vault_historial` DISABLE KEYS */;
/*!40000 ALTER TABLE `vault_historial` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `vault_permisos`
--

DROP TABLE IF EXISTS `vault_permisos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `vault_permisos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entrada_id` int(11) NOT NULL,
  `tipo` enum('rol','usuario','sucursal') NOT NULL,
  `referencia_id` int(11) NOT NULL COMMENT 'ID del rol, usuario o sucursal',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_entrada_tipo_ref` (`entrada_id`,`tipo`,`referencia_id`),
  KEY `idx_entrada` (`entrada_id`),
  CONSTRAINT `fk_perm_entrada` FOREIGN KEY (`entrada_id`) REFERENCES `vault_entradas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `vault_permisos`
--

LOCK TABLES `vault_permisos` WRITE;
/*!40000 ALTER TABLE `vault_permisos` DISABLE KEYS */;
/*!40000 ALTER TABLE `vault_permisos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'mantenimiento_bacal'
--

--
-- Dumping routines for database 'mantenimiento_bacal'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-06-23 18:11:15
