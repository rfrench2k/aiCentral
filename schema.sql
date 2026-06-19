/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `admin_lookups` (
  `ID` int NOT NULL AUTO_INCREMENT,
  `LOOKUP_NAME` varchar(100) NOT NULL,
  `LOOKUP_VALUE` varchar(100) NOT NULL,
  `LOOKUP_ORDER` int DEFAULT NULL,
  `LOOKUP_DESC` varchar(255) DEFAULT NULL,
  `ADD_DATE` datetime DEFAULT NULL,
  `DateTimeStamp` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_lookup_name` (`LOOKUP_NAME`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_api_pricing` (
  `pricing_id` int NOT NULL AUTO_INCREMENT,
  `ai_provider` varchar(20) NOT NULL,
  `model_name` varchar(50) NOT NULL,
  `input_cost_per_million` decimal(10,6) NOT NULL,
  `output_cost_per_million` decimal(10,6) NOT NULL,
  `effective_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`pricing_id`),
  KEY `idx_provider_model` (`ai_provider`,`model_name`),
  KEY `idx_dates` (`effective_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_database_credentials` (
  `credential_id` int NOT NULL AUTO_INCREMENT,
  `database_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission_level` enum('readonly','readwrite','full') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `db_host` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'localhost',
  `db_username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `encrypted_password` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `encryption_iv` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`credential_id`),
  UNIQUE KEY `unique_db_permission` (`database_name`,`permission_level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_feature_permissions` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `feature_id` int NOT NULL,
  `permission_type` enum('allow_db_read','allow_db_write','allow_db_delete','allow_temp_tables','allow_web_search','allow_web_fetch','allow_file_read','allow_file_write') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) DEFAULT '0',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `unique_feature_permission` (`feature_id`,`permission_type`),
  CONSTRAINT `ai_feature_permissions_ibfk_1` FOREIGN KEY (`feature_id`) REFERENCES `ai_features` (`feature_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_feature_tier_capabilities` (
  `feature_tier_capability_id` int NOT NULL AUTO_INCREMENT,
  `feature_id` int NOT NULL,
  `tier_id` int NOT NULL,
  `capability_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) DEFAULT '0',
  `max_uses` int DEFAULT NULL COMMENT 'NULL means unlimited',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`feature_tier_capability_id`),
  UNIQUE KEY `unique_feature_tier_capability` (`feature_id`,`tier_id`,`capability_code`),
  KEY `tier_id` (`tier_id`),
  CONSTRAINT `ai_feature_tier_capabilities_ibfk_1` FOREIGN KEY (`feature_id`) REFERENCES `ai_features` (`feature_id`) ON DELETE CASCADE,
  CONSTRAINT `ai_feature_tier_capabilities_ibfk_2` FOREIGN KEY (`tier_id`) REFERENCES `ai_tiers` (`tier_id`) ON DELETE CASCADE,
  CONSTRAINT `ai_feature_tier_capabilities_chk_1` CHECK (((`max_uses` is null) or ((`max_uses` >= 1) and (`max_uses` <= 1000))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-feature-per-tier capability configuration';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_feature_tier_config` (
  `feature_tier_config_id` int NOT NULL AUTO_INCREMENT,
  `feature_id` int NOT NULL,
  `tier_id` int NOT NULL,
  `max_output_tokens` int NOT NULL DEFAULT '4096',
  `model_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`feature_tier_config_id`),
  UNIQUE KEY `unique_feature_tier` (`feature_id`,`tier_id`),
  KEY `tier_id` (`tier_id`),
  CONSTRAINT `ai_feature_tier_config_ibfk_1` FOREIGN KEY (`feature_id`) REFERENCES `ai_features` (`feature_id`) ON DELETE CASCADE,
  CONSTRAINT `ai_feature_tier_config_ibfk_2` FOREIGN KEY (`tier_id`) REFERENCES `ai_tiers` (`tier_id`) ON DELETE CASCADE,
  CONSTRAINT `ai_feature_tier_config_chk_1` CHECK (((`max_output_tokens` >= 100) and (`max_output_tokens` <= 200000)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-feature-per-tier output token configuration';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_features` (
  `feature_id` int NOT NULL AUTO_INCREMENT,
  `program_id` varchar(20) NOT NULL,
  `feature_code` varchar(50) NOT NULL,
  `feature_name` varchar(200) NOT NULL,
  `feature_description` text,
  `feature_type` varchar(50) DEFAULT NULL,
  `default_provider` varchar(20) DEFAULT NULL,
  `default_model` varchar(100) DEFAULT NULL,
  `default_model_free` varchar(100) DEFAULT NULL,
  `default_model_basic` varchar(100) DEFAULT NULL,
  `default_model_pro` varchar(100) DEFAULT NULL,
  `default_model_unlimited` varchar(100) DEFAULT NULL,
  `supports_vision` tinyint(1) DEFAULT '0',
  `supports_streaming` tinyint(1) DEFAULT '0',
  `supports_file_upload` tinyint(1) DEFAULT '0',
  `gear_icon_visible` tinyint(1) DEFAULT '1',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '100',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `max_input_tokens` int DEFAULT NULL,
  `max_output_tokens` int DEFAULT NULL,
  `required_capabilities` json DEFAULT NULL,
  PRIMARY KEY (`feature_id`),
  UNIQUE KEY `unique_program_feature` (`program_id`,`feature_code`),
  KEY `idx_program_feature` (`program_id`,`feature_code`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `ai_features_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_model_capabilities` (
  `capability_id` int NOT NULL AUTO_INCREMENT,
  `model_id` int NOT NULL,
  `capability_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `capability_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `cost_per_use` decimal(10,6) DEFAULT '0.000000',
  `cost_per_1000` decimal(10,4) DEFAULT NULL,
  `includes_result_tokens` tinyint(1) DEFAULT '0',
  `is_free` tinyint(1) DEFAULT '0',
  `provider_tool_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `api_format_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `max_uses_default` int DEFAULT NULL,
  `is_supported` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`capability_id`),
  UNIQUE KEY `unique_model_capability` (`model_id`,`capability_code`),
  KEY `idx_model_capability` (`model_id`,`capability_code`),
  CONSTRAINT `ai_model_capabilities_ibfk_1` FOREIGN KEY (`model_id`) REFERENCES `ai_models` (`model_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_models` (
  `model_id` int NOT NULL AUTO_INCREMENT,
  `provider_id` int NOT NULL,
  `model_code` varchar(100) NOT NULL,
  `model_display_name` varchar(200) NOT NULL,
  `model_tier` varchar(20) DEFAULT 'standard',
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '100',
  `max_tokens` int DEFAULT '4096',
  `context_window` int DEFAULT '200000',
  `supports_vision` tinyint(1) DEFAULT '0',
  `supports_function_calling` tinyint(1) DEFAULT '0',
  `supports_streaming` tinyint(1) DEFAULT '1',
  `input_cost_per_million` decimal(10,6) NOT NULL,
  `output_cost_per_million` decimal(10,6) NOT NULL,
  `pricing_effective_date` date NOT NULL,
  `pricing_end_date` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` text,
  `processor_class` varchar(100) NOT NULL DEFAULT 'OpenAIProcessor',
  `thinking_cost_per_million` decimal(10,6) DEFAULT NULL,
  PRIMARY KEY (`model_id`),
  KEY `idx_provider_model` (`provider_id`,`model_code`),
  KEY `idx_active` (`is_active`),
  KEY `idx_tier` (`model_tier`),
  KEY `idx_pricing_dates` (`pricing_effective_date`,`pricing_end_date`),
  KEY `idx_processor` (`processor_class`),
  CONSTRAINT `ai_models_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `ai_providers` (`provider_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_providers` (
  `provider_id` int NOT NULL AUTO_INCREMENT,
  `provider_code` varchar(20) NOT NULL,
  `provider_name` varchar(100) NOT NULL,
  `api_endpoint` varchar(255) NOT NULL,
  `auth_header_type` varchar(20) NOT NULL,
  `auth_header_name` varchar(50) DEFAULT NULL,
  `api_version` varchar(20) DEFAULT NULL,
  `request_format` varchar(50) DEFAULT 'custom',
  `response_token_path` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `supports_vision` tinyint(1) DEFAULT '0',
  `supports_system_prompt` tinyint(1) DEFAULT '1',
  `max_context_window` int DEFAULT '200000',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text,
  PRIMARY KEY (`provider_id`),
  UNIQUE KEY `provider_code` (`provider_code`),
  KEY `idx_active` (`is_active`),
  KEY `idx_code` (`provider_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_settings` (
  `setting_id` int NOT NULL AUTO_INCREMENT,
  `setting_category` enum('system','user','program','feature') NOT NULL,
  `setting_scope_id` varchar(125) DEFAULT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_type` varchar(20) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_id`),
  UNIQUE KEY `unique_setting` (`setting_category`,`setting_scope_id`,`setting_key`),
  KEY `idx_category_scope` (`setting_category`,`setting_scope_id`),
  KEY `idx_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_tier_capabilities` (
  `tier_capability_id` int NOT NULL AUTO_INCREMENT,
  `tier_id` int NOT NULL,
  `capability_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_enabled` tinyint(1) DEFAULT '0',
  `max_uses` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tier_capability_id`),
  UNIQUE KEY `unique_tier_capability` (`tier_id`,`capability_code`),
  KEY `idx_tier_capability` (`tier_id`,`capability_code`),
  CONSTRAINT `ai_tier_capabilities_ibfk_1` FOREIGN KEY (`tier_id`) REFERENCES `ai_tiers` (`tier_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_tiers` (
  `tier_id` int NOT NULL AUTO_INCREMENT,
  `tier_code` varchar(20) NOT NULL,
  `tier_name` varchar(100) NOT NULL,
  `tier_description` text,
  `daily_request_limit` int DEFAULT NULL,
  `monthly_request_limit` int DEFAULT NULL,
  `daily_token_limit` int DEFAULT NULL,
  `monthly_token_limit` int DEFAULT NULL,
  `monthly_spend_limit_usd` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `sort_order` int DEFAULT '100',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tier_id`),
  UNIQUE KEY `tier_code` (`tier_code`),
  KEY `idx_code` (`tier_code`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ai_usage_log` (
  `usage_id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` varchar(125) NOT NULL,
  `program_id` varchar(20) NOT NULL,
  `feature_code` varchar(50) NOT NULL,
  `page_url` varchar(255) DEFAULT NULL,
  `provider_id` int NOT NULL,
  `model_id` int NOT NULL,
  `key_type` enum('system','user') NOT NULL,
  `key_id` int DEFAULT NULL,
  `input_tokens` int NOT NULL,
  `output_tokens` int NOT NULL,
  `total_tokens` int GENERATED ALWAYS AS ((`input_tokens` + `output_tokens`)) STORED,
  `input_cost_usd` decimal(10,6) NOT NULL,
  `output_cost_usd` decimal(10,6) NOT NULL,
  `total_cost_usd` decimal(10,6) GENERATED ALWAYS AS ((`input_cost_usd` + `output_cost_usd`)) STORED,
  `request_timestamp` datetime DEFAULT CURRENT_TIMESTAMP,
  `response_time_ms` int DEFAULT NULL,
  `run_id` varchar(50) DEFAULT NULL,
  `status` enum('success','failed') DEFAULT 'success',
  `error_message` text,
  `prompt_text` text,
  `response_text` text,
  `prompt_to_ai` mediumtext,
  `request_metadata` json DEFAULT NULL,
  `complete_ai_response` mediumtext,
  `tool_calls_json` text,
  `tool_call_count` int DEFAULT '0',
  `tool_call_cost_usd` decimal(10,6) DEFAULT '0.000000',
  `tool_result_tokens` int DEFAULT '0',
  `tool_result_cost_usd` decimal(10,6) DEFAULT '0.000000',
  `thinking_tokens` int DEFAULT '0',
  `thinking_cost_usd` decimal(10,6) DEFAULT '0.000000',
  `cache_read_tokens` int NOT NULL DEFAULT '0',
  `cache_write_tokens` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`usage_id`),
  KEY `idx_user_program` (`user_id`,`program_id`),
  KEY `idx_timestamp` (`request_timestamp`),
  KEY `idx_program_feature` (`program_id`,`feature_code`),
  KEY `idx_model` (`model_id`),
  KEY `idx_status` (`status`),
  KEY `idx_cost_analysis` (`user_id`,`request_timestamp`,`total_cost_usd`),
  KEY `ai_usage_log_ibfk_3` (`provider_id`),
  CONSTRAINT `ai_usage_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `ai_usage_log_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE,
  CONSTRAINT `ai_usage_log_ibfk_3` FOREIGN KEY (`provider_id`) REFERENCES `ai_providers` (`provider_id`) ON DELETE CASCADE,
  CONSTRAINT `ai_usage_log_ibfk_4` FOREIGN KEY (`model_id`) REFERENCES `ai_models` (`model_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `app_level_ai_mapping` (
  `mapping_id` int NOT NULL AUTO_INCREMENT,
  `app_level` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `ai_tier_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`mapping_id`),
  UNIQUE KEY `unique_app_level` (`app_level`),
  KEY `idx_tier_code` (`ai_tier_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_conversations` (
  `conversation_id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(125) NOT NULL,
  `program_id` varchar(20) NOT NULL,
  `chat_type_code` varchar(50) NOT NULL,
  `conversation_title` varchar(255) DEFAULT NULL,
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `last_message_at` datetime DEFAULT NULL,
  `message_count` int DEFAULT '0',
  `is_archived` tinyint(1) DEFAULT '0',
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`conversation_id`),
  KEY `idx_user_program` (`user_id`,`program_id`),
  KEY `idx_active` (`user_id`,`is_archived`,`last_message_at`),
  KEY `idx_expiration` (`expires_at`),
  KEY `chat_conversations_ibfk_2` (`program_id`),
  CONSTRAINT `chat_conversations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `chat_conversations_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_messages` (
  `message_id` bigint NOT NULL AUTO_INCREMENT,
  `conversation_id` int NOT NULL,
  `message_role` enum('user','assistant','system') NOT NULL,
  `message_text` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `usage_log_id` bigint DEFAULT NULL,
  `page_context` json DEFAULT NULL,
  PRIMARY KEY (`message_id`),
  KEY `idx_conversation` (`conversation_id`,`created_at`),
  KEY `idx_usage_log` (`usage_log_id`),
  CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`conversation_id`) ON DELETE CASCADE,
  CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`usage_log_id`) REFERENCES `ai_usage_log` (`usage_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_types` (
  `chat_type_id` int NOT NULL AUTO_INCREMENT,
  `program_id` varchar(20) NOT NULL,
  `chat_type_code` varchar(50) NOT NULL,
  `chat_type_name` varchar(200) NOT NULL,
  `system_prompt` text NOT NULL,
  `default_provider` varchar(20) DEFAULT NULL,
  `default_model` varchar(100) DEFAULT NULL,
  `allow_file_upload` tinyint(1) DEFAULT '0',
  `allow_image_upload` tinyint(1) DEFAULT '0',
  `allow_voice_input` tinyint(1) DEFAULT '0',
  `max_history_messages` int DEFAULT '50',
  `retention_days` int DEFAULT '90',
  `chatbot_style` varchar(20) DEFAULT 'sidebar',
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`chat_type_id`),
  UNIQUE KEY `unique_chat_type` (`program_id`,`chat_type_code`),
  KEY `idx_program_type` (`program_id`,`chat_type_code`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `chat_types_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `programs` (
  `program_id` varchar(20) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  `program_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `allow_ai_features` tinyint(1) DEFAULT '1',
  `passthrough_mode` tinyint(1) NOT NULL DEFAULT '0',
  `passthrough_tier_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`program_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_api_keys` (
  `key_id` int NOT NULL AUTO_INCREMENT,
  `provider_id` int NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `encrypted_api_key` text NOT NULL,
  `encryption_iv` varchar(32) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `usage_limit_daily` int DEFAULT NULL,
  `usage_limit_monthly` int DEFAULT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_id`),
  KEY `idx_provider_active` (`provider_id`,`is_active`),
  CONSTRAINT `system_api_keys_ibfk_1` FOREIGN KEY (`provider_id`) REFERENCES `ai_providers` (`provider_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tier_model_permissions` (
  `permission_id` int NOT NULL AUTO_INCREMENT,
  `tier_id` int NOT NULL,
  `model_id` int NOT NULL,
  `is_allowed` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `max_tokens_per_request` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`permission_id`),
  UNIQUE KEY `unique_tier_model` (`tier_id`,`model_id`),
  KEY `model_id` (`model_id`),
  KEY `idx_tier_model` (`tier_id`,`model_id`),
  CONSTRAINT `tier_model_permissions_ibfk_1` FOREIGN KEY (`tier_id`) REFERENCES `ai_tiers` (`tier_id`) ON DELETE CASCADE,
  CONSTRAINT `tier_model_permissions_ibfk_2` FOREIGN KEY (`model_id`) REFERENCES `ai_models` (`model_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_api_keys` (
  `key_id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(125) NOT NULL,
  `provider_id` int NOT NULL,
  `key_name` varchar(100) DEFAULT NULL,
  `encrypted_api_key` text NOT NULL,
  `encryption_iv` varchar(32) NOT NULL,
  `key_prefix` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `is_default` tinyint(1) DEFAULT '0',
  `last_used_at` datetime DEFAULT NULL,
  `last_test_status` enum('success','failed','untested') DEFAULT 'untested',
  `last_test_error` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_id`),
  KEY `idx_user_provider` (`user_id`,`provider_id`),
  KEY `idx_active` (`is_active`),
  KEY `idx_default` (`user_id`,`provider_id`,`is_default`),
  KEY `user_api_keys_ibfk_2` (`provider_id`),
  CONSTRAINT `user_api_keys_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `user_api_keys_ibfk_2` FOREIGN KEY (`provider_id`) REFERENCES `ai_providers` (`provider_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_feature_preferences` (
  `preference_id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(125) NOT NULL,
  `program_id` varchar(20) NOT NULL,
  `feature_code` varchar(50) NOT NULL,
  `use_user_key` tinyint(1) DEFAULT '0',
  `user_key_id` int DEFAULT NULL,
  `preferred_provider` varchar(20) DEFAULT NULL,
  `preferred_model` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`preference_id`),
  UNIQUE KEY `unique_pref` (`user_id`,`program_id`,`feature_code`),
  KEY `idx_user_program_feature` (`user_id`,`program_id`,`feature_code`),
  KEY `user_feature_preferences_ibfk_2` (`program_id`),
  KEY `user_feature_preferences_ibfk_3` (`user_key_id`),
  CONSTRAINT `user_feature_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `user_feature_preferences_ibfk_2` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE,
  CONSTRAINT `user_feature_preferences_ibfk_3` FOREIGN KEY (`user_key_id`) REFERENCES `user_api_keys` (`key_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_tier_assignments` (
  `assignment_id` int NOT NULL AUTO_INCREMENT,
  `user_id` varchar(125) NOT NULL,
  `tier_id` int NOT NULL,
  `program_id` varchar(20) DEFAULT NULL,
  `assigned_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `assigned_by` varchar(125) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`assignment_id`),
  UNIQUE KEY `unique_assignment` (`user_id`,`program_id`),
  KEY `tier_id` (`tier_id`),
  KEY `program_id` (`program_id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_user_program` (`user_id`,`program_id`),
  CONSTRAINT `user_tier_assignments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  CONSTRAINT `user_tier_assignments_ibfk_2` FOREIGN KEY (`tier_id`) REFERENCES `ai_tiers` (`tier_id`) ON DELETE CASCADE,
  CONSTRAINT `user_tier_assignments_ibfk_3` FOREIGN KEY (`program_id`) REFERENCES `programs` (`program_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `user_id` varchar(125) NOT NULL,
  `name` varchar(75) DEFAULT NULL,
  `email` varchar(125) DEFAULT NULL,
  `default_ai_tier` varchar(20) DEFAULT 'free',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_email` (`email`),
  KEY `idx_tier` (`default_ai_tier`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

