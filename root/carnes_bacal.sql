-- ============================================================================
-- SISTEMA DE BITÁCORA DE INCIDENCIAS - CARNES BACAL
-- ============================================================================
-- Base de datos: carnes_bacal
-- Motor: MySQL / MariaDB (XAMPP)
-- Charset: utf8mb4 (soporte completo de emojis y caracteres especiales)
-- ============================================================================

-- Eliminar base de datos si existe (solo para reinstalaciones limpias)
DROP DATABASE IF EXISTS carnes_bacal;

-- Crear la base de datos
CREATE DATABASE carnes_bacal
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE carnes_bacal;

-- ============================================================================
-- TABLAS DE CATÁLOGOS BASE
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Tabla: roles
-- Define los roles del sistema y sus permisos generales
-- ----------------------------------------------------------------------------
CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    -- Permisos generales del rol (flags rápidos)
    puede_administrar TINYINT(1) NOT NULL DEFAULT 0,      -- Acceso al panel admin
    puede_ver_todas_sucursales TINYINT(1) NOT NULL DEFAULT 0,
    puede_resolver TINYINT(1) NOT NULL DEFAULT 0,         -- Puede atender/resolver incidencias
    puede_crear_solicitud TINYINT(1) NOT NULL DEFAULT 1,  -- Puede crear nuevas incidencias
    puede_ver_reportes TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: sucursales
-- Sucursales de la empresa (Bacal, Ferias, futuras)
-- ----------------------------------------------------------------------------
CREATE TABLE sucursales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    codigo VARCHAR(20) NOT NULL UNIQUE,        -- Código corto para folios (BAC, FER)
    direccion VARCHAR(255),
    telefono VARCHAR(50),
    responsable VARCHAR(150),
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: areas
-- Áreas/departamentos. Son globales (mismas para todas las sucursales)
-- ----------------------------------------------------------------------------
CREATE TABLE areas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    color VARCHAR(20) DEFAULT '#6B7280',       -- Color para identificación visual (tipo Notion)
    icono VARCHAR(50),                          -- Nombre de ícono opcional (lucide/heroicons)
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: categorias
-- Categorías técnicas de la incidencia (Hardware, Software, Red, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    color VARCHAR(20) DEFAULT '#6B7280',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: subcategorias
-- Subcategorías opcionales (Impresoras, Red WiFi, Punto de Venta, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE subcategorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255),
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE CASCADE,
    UNIQUE KEY uk_categoria_nombre (categoria_id, nombre)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: tipos_trabajo
-- Tipos de trabajo realizado (PC, Alarmas, Cámaras, etc., como en tu Notion)
-- ----------------------------------------------------------------------------
CREATE TABLE tipos_trabajo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL UNIQUE,
    descripcion VARCHAR(255),
    color VARCHAR(20) DEFAULT '#6B7280',
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: severidades
-- Nivel de prioridad/severidad (Crítica, Alta, Media, Baja)
-- ----------------------------------------------------------------------------
CREATE TABLE severidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    nivel INT NOT NULL UNIQUE,                  -- 1=Crítica, 2=Alta, 3=Media, 4=Baja
    color VARCHAR(20) NOT NULL DEFAULT '#6B7280',
    sla_horas INT,                              -- Tiempo máximo de respuesta en horas (opcional)
    descripcion VARCHAR(255),
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: estados
-- Estados posibles de una incidencia (Abierta, En proceso, Resuelta, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE estados (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    orden INT NOT NULL,                         -- Orden lógico para flujo
    color VARCHAR(20) NOT NULL DEFAULT '#6B7280',
    es_inicial TINYINT(1) NOT NULL DEFAULT 0,   -- ¿Es el estado al crear?
    es_final TINYINT(1) NOT NULL DEFAULT 0,     -- ¿Es estado de cierre?
    descripcion VARCHAR(255),
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: origenes_reporte
-- De dónde proviene el reporte (Presencial, Telefónico, Sistema, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE origenes_reporte (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

-- ============================================================================
-- TABLAS DE USUARIOS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Tabla: usuarios
-- Usuarios del sistema (ingenieros, gerentes, jefes de área)
-- ----------------------------------------------------------------------------
CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) NOT NULL UNIQUE,         -- Nombre de usuario para login
    password_hash VARCHAR(255) NOT NULL,         -- Hash bcrypt/argon2
    nombre_completo VARCHAR(150) NOT NULL,
    email VARCHAR(150),
    telefono VARCHAR(50),
    rol_id INT NOT NULL,
    sucursal_id INT,                             -- NULL = puede ver todas (para ingenieros/admin)
    area_id INT,                                 -- Área a la que pertenece (sobre todo para jefes)
    puesto VARCHAR(100),                         -- Puesto formal en la empresa
    avatar VARCHAR(255),                         -- Ruta a foto opcional
    activo TINYINT(1) NOT NULL DEFAULT 1,
    ultimo_login DATETIME,
    intentos_fallidos INT NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME,
    debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (rol_id) REFERENCES roles(id),
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id) ON DELETE SET NULL,
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,
    INDEX idx_usuario_activo (usuario, activo),
    INDEX idx_sucursal (sucursal_id),
    INDEX idx_area (area_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: sesiones
-- Control de sesiones activas (opcional pero útil para "cerrar sesión remota")
-- ----------------------------------------------------------------------------
CREATE TABLE sesiones (
    id VARCHAR(128) PRIMARY KEY,                 -- ID de sesión PHP
    usuario_id INT NOT NULL,
    ip VARCHAR(45),
    user_agent VARCHAR(255),
    creada_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    ultima_actividad DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_actividad (ultima_actividad)
) ENGINE=InnoDB;

-- ============================================================================
-- TABLA DE EQUIPOS / ACTIVOS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Tabla: equipos
-- Inventario de equipos/activos por sucursal
-- ----------------------------------------------------------------------------
CREATE TABLE equipos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_inventario VARCHAR(50) NOT NULL UNIQUE,  -- Etiqueta de inventario
    nombre VARCHAR(150) NOT NULL,                   -- Nombre descriptivo (ej. "Caja 1 Bacal")
    tipo VARCHAR(50),                               -- PC, Impresora, Cámara, etc.
    marca VARCHAR(100),
    modelo VARCHAR(100),
    numero_serie VARCHAR(100),
    sucursal_id INT NOT NULL,
    area_id INT,
    ubicacion VARCHAR(255),                         -- Ubicación física específica
    responsable_id INT,                             -- Usuario responsable del equipo
    fecha_adquisicion DATE,
    notas TEXT,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id),
    FOREIGN KEY (area_id) REFERENCES areas(id) ON DELETE SET NULL,
    FOREIGN KEY (responsable_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_sucursal_area (sucursal_id, area_id),
    INDEX idx_tipo (tipo)
) ENGINE=InnoDB;

-- ============================================================================
-- TABLA PRINCIPAL: INCIDENCIAS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Tabla: incidencias
-- El corazón del sistema: cada registro de la bitácora
-- ----------------------------------------------------------------------------
CREATE TABLE incidencias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio VARCHAR(30) NOT NULL UNIQUE,           -- Folio amigable: INC-BAC-2026-0001

    -- Clasificación
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT NOT NULL,                   -- Descripción detallada del problema
    sucursal_id INT NOT NULL,
    area_id INT NOT NULL,
    categoria_id INT,
    subcategoria_id INT,
    tipo_trabajo_id INT,
    severidad_id INT NOT NULL,
    estado_id INT NOT NULL,
    origen_reporte_id INT,
    equipo_id INT,                               -- Equipo involucrado (opcional)

    -- Personas
    reportado_por_id INT NOT NULL,               -- Usuario que crea la incidencia
    reportante_nombre VARCHAR(150),              -- Si reporta alguien que no es usuario del sistema
    reportante_puesto VARCHAR(100),
    asignado_a_id INT,                           -- Técnico/ingeniero asignado
    resuelto_por_id INT,                         -- Quien efectivamente la resolvió

    -- Contenido de resolución
    causa_raiz TEXT,                             -- Causa identificada
    solucion TEXT,                               -- Solución aplicada
    recomendaciones TEXT,                        -- Recomendaciones para evitar recurrencia
    acciones_preventivas TEXT,                   -- Acciones preventivas tomadas

    -- Reincidencia
    es_reincidencia TINYINT(1) NOT NULL DEFAULT 0,
    incidencia_padre_id INT,                     -- Incidencia original relacionada
    veces_recurrida INT NOT NULL DEFAULT 0,      -- Contador (se calcula)

    -- Tiempos
    fecha_evento DATETIME NOT NULL,              -- Cuándo ocurrió el incidente
    fecha_atencion DATETIME,                     -- Cuándo se empezó a atender
    fecha_resolucion DATETIME,                   -- Cuándo se resolvió
    fecha_cierre DATETIME,                       -- Cuándo se cerró formalmente
    tiempo_respuesta_min INT,                    -- Minutos entre evento y atención
    tiempo_resolucion_min INT,                   -- Minutos entre atención y resolución

    -- SLA
    sla_cumplido TINYINT(1),                     -- Calculado: NULL si no aplica/no cerrado
    fecha_limite_sla DATETIME,

    -- Validación post-cierre
    confirmado_por_reportante TINYINT(1) DEFAULT 0,
    fecha_confirmacion DATETIME,
    calificacion_servicio INT,                   -- 1-5 estrellas, opcional
    comentario_reportante TEXT,

    -- Metadata
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    creado_por_id INT NOT NULL,                  -- Quien registró en el sistema
    actualizado_en DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    actualizado_por_id INT,

    FOREIGN KEY (sucursal_id) REFERENCES sucursales(id),
    FOREIGN KEY (area_id) REFERENCES areas(id),
    FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
    FOREIGN KEY (subcategoria_id) REFERENCES subcategorias(id) ON DELETE SET NULL,
    FOREIGN KEY (tipo_trabajo_id) REFERENCES tipos_trabajo(id) ON DELETE SET NULL,
    FOREIGN KEY (severidad_id) REFERENCES severidades(id),
    FOREIGN KEY (estado_id) REFERENCES estados(id),
    FOREIGN KEY (origen_reporte_id) REFERENCES origenes_reporte(id) ON DELETE SET NULL,
    FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE SET NULL,
    FOREIGN KEY (reportado_por_id) REFERENCES usuarios(id),
    FOREIGN KEY (asignado_a_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (resuelto_por_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    FOREIGN KEY (incidencia_padre_id) REFERENCES incidencias(id) ON DELETE SET NULL,
    FOREIGN KEY (creado_por_id) REFERENCES usuarios(id),
    FOREIGN KEY (actualizado_por_id) REFERENCES usuarios(id) ON DELETE SET NULL,

    INDEX idx_folio (folio),
    INDEX idx_sucursal_estado (sucursal_id, estado_id),
    INDEX idx_area (area_id),
    INDEX idx_severidad (severidad_id),
    INDEX idx_asignado (asignado_a_id),
    INDEX idx_reportado_por (reportado_por_id),
    INDEX idx_equipo (equipo_id),
    INDEX idx_fecha_evento (fecha_evento),
    INDEX idx_reincidencia (es_reincidencia, incidencia_padre_id),
    INDEX idx_busqueda_reincidencia (equipo_id, categoria_id, fecha_evento)
) ENGINE=InnoDB;

-- ============================================================================
-- TABLAS RELACIONADAS A INCIDENCIAS
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Tabla: incidencias_adjuntos
-- Imágenes y archivos adjuntos a cada incidencia
-- ----------------------------------------------------------------------------
CREATE TABLE incidencias_adjuntos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incidencia_id INT NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,       -- Nombre del archivo subido
    nombre_archivo VARCHAR(255) NOT NULL,        -- Nombre real en disco (con hash)
    ruta VARCHAR(500) NOT NULL,                  -- Ruta relativa al archivo
    tipo_mime VARCHAR(100),
    tamano_bytes INT,
    momento VARCHAR(20) DEFAULT 'durante',       -- 'antes', 'durante', 'despues', 'evidencia'
    descripcion VARCHAR(255),
    subido_por_id INT NOT NULL,
    subido_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incidencia_id) REFERENCES incidencias(id) ON DELETE CASCADE,
    FOREIGN KEY (subido_por_id) REFERENCES usuarios(id),
    INDEX idx_incidencia (incidencia_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: incidencias_comentarios
-- Comentarios/notas adicionales en el timeline de cada incidencia
-- ----------------------------------------------------------------------------
CREATE TABLE incidencias_comentarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incidencia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    comentario TEXT NOT NULL,
    es_interno TINYINT(1) NOT NULL DEFAULT 0,    -- Solo visible para staff de sistemas
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incidencia_id) REFERENCES incidencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_incidencia (incidencia_id)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: incidencias_historial
-- Auditoría: cada cambio en una incidencia queda registrado
-- ----------------------------------------------------------------------------
CREATE TABLE incidencias_historial (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incidencia_id INT NOT NULL,
    usuario_id INT NOT NULL,
    accion VARCHAR(50) NOT NULL,                 -- 'creada', 'estado_cambiado', 'asignada', etc.
    campo VARCHAR(100),                          -- Campo modificado
    valor_anterior TEXT,
    valor_nuevo TEXT,
    descripcion VARCHAR(500),
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incidencia_id) REFERENCES incidencias(id) ON DELETE CASCADE,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    INDEX idx_incidencia (incidencia_id),
    INDEX idx_fecha (creado_en)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: incidencias_etiquetas
-- Etiquetas libres para búsqueda flexible (tags)
-- ----------------------------------------------------------------------------
CREATE TABLE incidencias_etiquetas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    incidencia_id INT NOT NULL,
    etiqueta VARCHAR(50) NOT NULL,
    FOREIGN KEY (incidencia_id) REFERENCES incidencias(id) ON DELETE CASCADE,
    UNIQUE KEY uk_incidencia_etiqueta (incidencia_id, etiqueta),
    INDEX idx_etiqueta (etiqueta)
) ENGINE=InnoDB;

-- ============================================================================
-- AUDITORÍA GENERAL DEL SISTEMA
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Tabla: auditoria_sistema
-- Registra acciones importantes en todo el sistema (logins, cambios admin, etc.)
-- ----------------------------------------------------------------------------
CREATE TABLE auditoria_sistema (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT,
    accion VARCHAR(100) NOT NULL,                -- 'login', 'logout', 'crear_usuario', etc.
    entidad VARCHAR(50),                         -- 'usuarios', 'areas', etc.
    entidad_id INT,
    descripcion TEXT,
    ip VARCHAR(45),
    user_agent VARCHAR(255),
    creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    INDEX idx_usuario (usuario_id),
    INDEX idx_accion (accion),
    INDEX idx_fecha (creado_en)
) ENGINE=InnoDB;

-- ----------------------------------------------------------------------------
-- Tabla: notificaciones
-- Notificaciones in-app para usuarios
-- ----------------------------------------------------------------------------
CREATE TABLE notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,                   -- 'nueva_incidencia', 'asignacion', etc.
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT,
    enlace VARCHAR(500),                         -- URL a donde lleva al hacer clic
    leida TINYINT(1) NOT NULL DEFAULT 0,
    leida_en DATETIME,
    creada_en DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_leida (usuario_id, leida),
    INDEX idx_fecha (creada_en)
) ENGINE=InnoDB;

-- ============================================================================
-- DATOS INICIALES (SEMILLAS)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- ROLES del sistema
-- ----------------------------------------------------------------------------
INSERT INTO roles (nombre, descripcion, puede_administrar, puede_ver_todas_sucursales, puede_resolver, puede_crear_solicitud, puede_ver_reportes) VALUES
('Administrador', 'Control total del sistema, configura todo', 1, 1, 1, 1, 1),
('Ingeniero en Sistemas', 'Atiende y resuelve incidencias en todas las sucursales', 0, 1, 1, 1, 1),
('Gerente', 'Supervisa su sucursal y genera reportes', 0, 0, 0, 1, 1),
('Jefe de Área', 'Crea solicitudes de su área y da seguimiento', 0, 0, 0, 1, 0),
('Solo Lectura', 'Consulta y filtra sin modificar', 0, 1, 0, 0, 1);

-- ----------------------------------------------------------------------------
-- SUCURSALES iniciales
-- ----------------------------------------------------------------------------
INSERT INTO sucursales (nombre, codigo, direccion, telefono) VALUES
('Bacal', 'BAC', 'Av. Cruz del Sur 2025, Fracc. Las Huertas 3ra. Sección, Tijuana', '(664) 972 06 31'),
('Ferias', 'FER', 'Por definir', '');

-- ----------------------------------------------------------------------------
-- ÁREAS globales (tomadas de tu Notion)
-- ----------------------------------------------------------------------------
INSERT INTO areas (nombre, color) VALUES
('Cajas',                '#D97706'),  -- naranja
('Contabilidad',         '#DC2626'),  -- rojo
('Gerencia',             '#2563EB'),  -- azul
('Auditoría',            '#7C3AED'),  -- morado
('Almacén',              '#9333EA'),  -- violeta
('Pedidos',              '#EA580C'),  -- naranja oscuro
('Seguridad e Higiene',  '#16A34A'),  -- verde
('Diseño',               '#22C55E'),  -- verde claro
('RH',                   '#6B7280'),  -- gris
('Reparto',              '#EA580C'),
('Carnicería',           '#2563EB'),
('Cuarto Frío',          '#D97706'),
('Mantenimiento',        '#6B7280'),
('Proyectos Especiales', '#7C3AED'),
('Oficina',              '#6B7280'),
('Cocina',               '#16A34A'),
('Guardias',             '#9333EA'),
('Taller',               '#DC2626');

-- ----------------------------------------------------------------------------
-- CATEGORÍAS técnicas (precargas genéricas, las refinamos en el panel)
-- ----------------------------------------------------------------------------
INSERT INTO categorias (nombre, color) VALUES
('Hardware',           '#DC2626'),
('Software',           '#2563EB'),
('Red e Internet',     '#16A34A'),
('Telefonía',          '#7C3AED'),
('Seguridad',          '#EA580C'),
('Punto de Venta',     '#D97706'),
('Cámaras CCTV',       '#9333EA'),
('Alarmas',            '#DC2626'),
('Impresión',          '#6B7280'),
('Soporte a usuario',  '#22C55E'),
('Mantenimiento',      '#0EA5E9'),
('Otro',               '#6B7280');

-- ----------------------------------------------------------------------------
-- SUBCATEGORÍAS (ejemplos, ampliable desde el panel)
-- ----------------------------------------------------------------------------
INSERT INTO subcategorias (categoria_id, nombre) VALUES
((SELECT id FROM categorias WHERE nombre='Hardware'), 'PC'),
((SELECT id FROM categorias WHERE nombre='Hardware'), 'Laptop'),
((SELECT id FROM categorias WHERE nombre='Hardware'), 'Periféricos'),
((SELECT id FROM categorias WHERE nombre='Hardware'), 'Disco duro'),
((SELECT id FROM categorias WHERE nombre='Software'), 'Sistema operativo'),
((SELECT id FROM categorias WHERE nombre='Software'), 'Office'),
((SELECT id FROM categorias WHERE nombre='Software'), 'Sistema de punto de venta'),
((SELECT id FROM categorias WHERE nombre='Software'), 'Antivirus'),
((SELECT id FROM categorias WHERE nombre='Red e Internet'), 'WiFi'),
((SELECT id FROM categorias WHERE nombre='Red e Internet'), 'Cableado'),
((SELECT id FROM categorias WHERE nombre='Red e Internet'), 'Internet'),
((SELECT id FROM categorias WHERE nombre='Red e Internet'), 'VPN'),
((SELECT id FROM categorias WHERE nombre='Soporte a usuario'), 'Contraseña'),
((SELECT id FROM categorias WHERE nombre='Soporte a usuario'), 'Creación de cuenta'),
((SELECT id FROM categorias WHERE nombre='Soporte a usuario'), 'Permisos'),
((SELECT id FROM categorias WHERE nombre='Impresión'), 'Tóner / cartuchos'),
((SELECT id FROM categorias WHERE nombre='Impresión'), 'Atasco de papel'),
((SELECT id FROM categorias WHERE nombre='Impresión'), 'Configuración');

-- ----------------------------------------------------------------------------
-- TIPOS DE TRABAJO (basado en tu Notion: PC, Alarmas, etc.)
-- ----------------------------------------------------------------------------
INSERT INTO tipos_trabajo (nombre, color) VALUES
('PC',                     '#DC2626'),
('Alarmas',                '#EA580C'),
('Cámaras',                '#7C3AED'),
('Red',                    '#16A34A'),
('Impresora',              '#6B7280'),
('Punto de Venta',         '#D97706'),
('Telefonía',              '#2563EB'),
('Mantenimiento Preventivo','#0EA5E9'),
('Mantenimiento Correctivo','#DC2626'),
('Instalación',            '#22C55E'),
('Actualización',          '#9333EA'),
('Respaldo',               '#6B7280'),
('Capacitación',           '#0EA5E9'),
('Otro',                   '#6B7280');

-- ----------------------------------------------------------------------------
-- SEVERIDADES
-- ----------------------------------------------------------------------------
INSERT INTO severidades (nombre, nivel, color, sla_horas, descripcion) VALUES
('Crítica', 1, '#DC2626', 2,   'Operación detenida, requiere atención inmediata'),
('Alta',    2, '#EA580C', 8,   'Afectación importante a la operación'),
('Media',   3, '#D97706', 24,  'Afectación parcial, no detiene la operación'),
('Baja',    4, '#16A34A', 72,  'Sin afectación operativa, mejora o solicitud');

-- ----------------------------------------------------------------------------
-- ESTADOS del flujo de incidencias
-- ----------------------------------------------------------------------------
INSERT INTO estados (nombre, orden, color, es_inicial, es_final, descripcion) VALUES
('Abierta',     1, '#DC2626', 1, 0, 'Recién registrada, sin atender'),
('Asignada',    2, '#EA580C', 0, 0, 'Asignada a un técnico'),
('En proceso',  3, '#D97706', 0, 0, 'Siendo atendida activamente'),
('En espera',   4, '#6B7280', 0, 0, 'Esperando información, partes o terceros'),
('Resuelta',    5, '#0EA5E9', 0, 0, 'Solucionada, pendiente de confirmación'),
('Completada',  6, '#16A34A', 0, 1, 'Confirmada y cerrada'),
('Cancelada',   7, '#6B7280', 0, 1, 'Anulada sin resolución');

-- ----------------------------------------------------------------------------
-- ORÍGENES de reporte
-- ----------------------------------------------------------------------------
INSERT INTO origenes_reporte (nombre) VALUES
('Presencial'),
('Telefónico'),
('WhatsApp'),
('Correo electrónico'),
('Sistema'),
('Mantenimiento programado'),
('Otro');

-- ----------------------------------------------------------------------------
-- USUARIO ADMINISTRADOR INICIAL
-- Usuario: admin
-- Contraseña: admin123 (CAMBIAR EN PRIMER LOGIN)
-- Hash generado con password_hash('admin123', PASSWORD_DEFAULT) en PHP
-- ----------------------------------------------------------------------------
INSERT INTO usuarios (usuario, password_hash, nombre_completo, email, rol_id, sucursal_id, puesto, debe_cambiar_password) VALUES
('admin',
 '$2y$10$YourHashWillBeReplacedOnFirstSetup.WeWillGenerateRealOneInPHP',
 'Administrador del Sistema',
 'admin@carnesbacal.com',
 (SELECT id FROM roles WHERE nombre='Administrador'),
 NULL,
 'Administrador',
 1);

-- ============================================================================
-- VISTAS ÚTILES (para simplificar consultas frecuentes)
-- ============================================================================

-- ----------------------------------------------------------------------------
-- Vista: v_incidencias_completas
-- Une la información de incidencias con todos sus catálogos relacionados
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_incidencias_completas AS
SELECT
    i.id,
    i.folio,
    i.titulo,
    i.descripcion,
    i.fecha_evento,
    i.fecha_atencion,
    i.fecha_resolucion,
    i.fecha_cierre,
    i.tiempo_respuesta_min,
    i.tiempo_resolucion_min,
    i.es_reincidencia,
    i.veces_recurrida,
    i.incidencia_padre_id,
    i.solucion,
    i.recomendaciones,
    i.causa_raiz,
    i.sla_cumplido,
    i.creado_en,
    s.id AS sucursal_id,
    s.nombre AS sucursal_nombre,
    s.codigo AS sucursal_codigo,
    a.id AS area_id,
    a.nombre AS area_nombre,
    a.color AS area_color,
    c.id AS categoria_id,
    c.nombre AS categoria_nombre,
    c.color AS categoria_color,
    sc.nombre AS subcategoria_nombre,
    tt.id AS tipo_trabajo_id,
    tt.nombre AS tipo_trabajo_nombre,
    tt.color AS tipo_trabajo_color,
    sev.id AS severidad_id,
    sev.nombre AS severidad_nombre,
    sev.color AS severidad_color,
    sev.nivel AS severidad_nivel,
    e.id AS estado_id,
    e.nombre AS estado_nombre,
    e.color AS estado_color,
    e.es_final AS estado_es_final,
    eq.id AS equipo_id,
    eq.codigo_inventario AS equipo_codigo,
    eq.nombre AS equipo_nombre,
    rep.id AS reportado_por_id,
    rep.nombre_completo AS reportado_por_nombre,
    i.reportante_nombre,
    asig.id AS asignado_a_id,
    asig.nombre_completo AS asignado_a_nombre,
    res.id AS resuelto_por_id,
    res.nombre_completo AS resuelto_por_nombre
FROM incidencias i
LEFT JOIN sucursales s ON i.sucursal_id = s.id
LEFT JOIN areas a ON i.area_id = a.id
LEFT JOIN categorias c ON i.categoria_id = c.id
LEFT JOIN subcategorias sc ON i.subcategoria_id = sc.id
LEFT JOIN tipos_trabajo tt ON i.tipo_trabajo_id = tt.id
LEFT JOIN severidades sev ON i.severidad_id = sev.id
LEFT JOIN estados e ON i.estado_id = e.id
LEFT JOIN equipos eq ON i.equipo_id = eq.id
LEFT JOIN usuarios rep ON i.reportado_por_id = rep.id
LEFT JOIN usuarios asig ON i.asignado_a_id = asig.id
LEFT JOIN usuarios res ON i.resuelto_por_id = res.id;

-- ----------------------------------------------------------------------------
-- Vista: v_estadisticas_sucursal
-- KPIs rápidos por sucursal
-- ----------------------------------------------------------------------------
CREATE OR REPLACE VIEW v_estadisticas_sucursal AS
SELECT
    s.id AS sucursal_id,
    s.nombre AS sucursal_nombre,
    COUNT(i.id) AS total_incidencias,
    SUM(CASE WHEN e.es_final = 0 THEN 1 ELSE 0 END) AS abiertas,
    SUM(CASE WHEN e.es_final = 1 THEN 1 ELSE 0 END) AS cerradas,
    SUM(CASE WHEN i.es_reincidencia = 1 THEN 1 ELSE 0 END) AS reincidencias,
    SUM(CASE WHEN sev.nivel = 1 AND e.es_final = 0 THEN 1 ELSE 0 END) AS criticas_abiertas,
    AVG(i.tiempo_resolucion_min) AS tiempo_promedio_resolucion_min
FROM sucursales s
LEFT JOIN incidencias i ON i.sucursal_id = s.id
LEFT JOIN estados e ON i.estado_id = e.id
LEFT JOIN severidades sev ON i.severidad_id = sev.id
GROUP BY s.id, s.nombre;

-- ============================================================================
-- FIN DEL SCRIPT
-- ============================================================================
-- Después de importar este script:
-- 1. Acceder al sistema con usuario 'admin' y contraseña 'admin123'
--    (el hash real se generará desde PHP en el primer setup, ver Paso 2)
-- 2. El sistema obligará a cambiar la contraseña en el primer login
-- 3. Desde el panel de administración:
--    - Completar la dirección de la sucursal Ferias
--    - Crear las cuentas de los 3 ingenieros, 3 gerentes y jefes de área
--    - Agregar equipos al inventario
--    - Ajustar catálogos según las necesidades reales
-- ============================================================================
