import * as Joi from 'joi';

export const envValidationSchema = Joi.object({
  NODE_ENV: Joi.string().valid('development', 'test', 'production').default('development'),
  PORT: Joi.number().integer().min(1).max(65535).default(3000),
  LOG_LEVEL: Joi.string().valid('log', 'error', 'warn', 'debug', 'verbose').default('log'),
  REQUEST_TIMEOUT_MS: Joi.number().integer().min(100).default(10000),

  MONGO_URI: Joi.string().uri({ scheme: [/mongodb/] }).required(),
  MONGOOSE_DEBUG: Joi.boolean().truthy('true').falsy('false').default(false),
  MONGO_MAX_POOL_SIZE: Joi.number().integer().min(1).default(20),
  MONGO_MIN_POOL_SIZE: Joi.number().integer().min(0).default(5),
  MONGO_MAX_IDLE_MS: Joi.number().integer().min(0).default(60000),
  MONGO_SERVER_SELECTION_TIMEOUT_MS: Joi.number().integer().min(1000).default(10000),
  MONGO_SOCKET_TIMEOUT_MS: Joi.number().integer().min(1000).default(45000),

  JWT_SECRET: Joi.string().min(16).required(), // ไทย: ต้องมีความยาวพอสมควร
  JWT_EXPIRES: Joi.string().default('1h'),
  BCRYPT_SALT_ROUNDS: Joi.number().integer().min(10).max(14).default(12),

  MONGO_ENABLE_ZSTD: Joi.boolean().truthy('true').falsy('false').default(false),
  MONGO_ENABLE_SNAPPY: Joi.boolean().truthy('true').falsy('false').default(false),
});
