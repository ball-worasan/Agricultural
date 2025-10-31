export default () => {
  // รองรับทั้ง MONGO_URI และ MONGODB_URI (fallback)
  const mongoUri = process.env.MONGO_URI || process.env.MONGODB_URI || '';

  // รวมถึง 'fatal' ใน union ของ logLevel
  const logLevel = (process.env.LOG_LEVEL ?? 'log') as
    | 'fatal'
    | 'error'
    | 'warn'
    | 'log'
    | 'debug'
    | 'verbose';

  return {
    nodeEnv: process.env.NODE_ENV ?? 'development',
    port: parseInt(process.env.PORT ?? '3000', 10),
    logLevel,
    requestTimeoutMs: parseInt(process.env.REQUEST_TIMEOUT_MS ?? '10000', 10),

    // CORS allowlist (คอมม่าแยกรายการ)
    corsAllowlist: process.env.CORS_ALLOWLIST || '',

    mongo: {
      uri: mongoUri,
      debug: (process.env.MONGOOSE_DEBUG ?? 'false') === 'true',
      maxPoolSize: parseInt(process.env.MONGO_MAX_POOL_SIZE ?? '20', 10),
      minPoolSize: parseInt(process.env.MONGO_MIN_POOL_SIZE ?? '5', 10),
      maxIdleTimeMS: parseInt(process.env.MONGO_MAX_IDLE_MS ?? '60000', 10),
      serverSelectionTimeoutMS: parseInt(
        process.env.MONGO_SERVER_SELECTION_TIMEOUT_MS ?? '10000',
        10,
      ),
      socketTimeoutMS: parseInt(
        process.env.MONGO_SOCKET_TIMEOUT_MS ?? '45000',
        10,
      ),

      // ควบคุม write concern / retry จาก ENV
      retryWrites: (process.env.MONGO_RETRY_WRITES ?? 'true') === 'true',
      w: process.env.MONGO_W ?? 'majority',
    },

    auth: {
      jwtSecret: process.env.JWT_SECRET || 'change-me',
      jwtExpires: process.env.JWT_EXPIRES || '1h',
      bcryptSaltRounds: parseInt(process.env.BCRYPT_SALT_ROUNDS ?? '12', 10),
    },
  };
};
