export default () => ({
  nodeEnv: process.env.NODE_ENV ?? 'development',
  port: parseInt(process.env.PORT ?? '3000', 10),
  logLevel: (process.env.LOG_LEVEL ?? 'log') as 'log' | 'error' | 'warn' | 'debug' | 'verbose',
  requestTimeoutMs: parseInt(process.env.REQUEST_TIMEOUT_MS ?? '10000', 10),

  mongo: {
    uri: process.env.MONGO_URI as string,
    debug: (process.env.MONGOOSE_DEBUG ?? 'false') === 'true',
    maxPoolSize: parseInt(process.env.MONGO_MAX_POOL_SIZE ?? '20', 10),
    minPoolSize: parseInt(process.env.MONGO_MIN_POOL_SIZE ?? '5', 10),
    maxIdleTimeMS: parseInt(process.env.MONGO_MAX_IDLE_MS ?? '60000', 10),
    serverSelectionTimeoutMS: parseInt(process.env.MONGO_SERVER_SELECTION_TIMEOUT_MS ?? '10000', 10),
    socketTimeoutMS: parseInt(process.env.MONGO_SOCKET_TIMEOUT_MS ?? '45000', 10),
  },

  auth: {
    jwtSecret: process.env.JWT_SECRET || 'change-me',
    jwtExpires: process.env.JWT_EXPIRES || '1h',
    bcryptSaltRounds: parseInt(process.env.BCRYPT_SALT_ROUNDS ?? '12', 10),
  },
});
