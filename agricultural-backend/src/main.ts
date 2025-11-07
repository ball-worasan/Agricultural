import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import {
  Logger,
  LogLevel,
  ValidationPipe,
  VersioningType,
  RequestMethod,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import helmet from 'helmet';
import compression from 'compression';
import { AllExceptionsFilter } from './common/filters/http-exception.filter';
import { TimeoutInterceptor } from './common/interceptors/timeout.interceptor';
import mongoose from 'mongoose';
import { randomUUID } from 'crypto';
import { NestExpressApplication } from '@nestjs/platform-express';
import { setupSwagger } from './swagger';

async function bootstrap() {
  const app = await NestFactory.create<NestExpressApplication>(AppModule, {
    bufferLogs: true,
  });
  const cfg = app.get(ConfigService);
  const logger = new Logger('Bootstrap');

  // === Log levels: mapping ตามความ "เคร่ง → ผ่อน" ===
  const level = cfg.get<LogLevel>('logLevel', 'log');
  const levelsByVerbosity: Record<LogLevel, LogLevel[]> = {
    fatal: ['fatal'],
    error: ['fatal', 'error'],
    warn: ['fatal', 'error', 'warn'],
    log: ['fatal', 'error', 'warn', 'log'],
    debug: ['fatal', 'error', 'warn', 'log', 'debug'],
    verbose: ['fatal', 'error', 'warn', 'log', 'debug', 'verbose'],
  };
  app.useLogger(levelsByVerbosity[level] ?? ['fatal', 'error', 'warn', 'log']);

  // === Proxy/Helmet/Compression ===
  app.set('trust proxy', 1); // หากรันหลัง Nginx/Ingress ให้เชื่อ IP/X-Forwarded-*
  app.use(
    helmet({
      contentSecurityPolicy: false, // เปิด CSP ทีหลังเมื่อ Front พร้อม
      crossOriginResourcePolicy: { policy: 'cross-origin' },
      hsts:
        cfg.get('nodeEnv') === 'production'
          ? { maxAge: 15552000, includeSubDomains: true, preload: true }
          : false,
    }),
  );
  app.use(compression());

  // === request-id middleware: สร้าง/ส่งต่อ x-request-id ===
  app.use((req: any, res: any, next: () => void) => {
    const rid = (req.headers['x-request-id'] as string) || randomUUID();
    req.headers['x-request-id'] = rid;
    res.setHeader('x-request-id', rid);
    next();
  });

  // === CORS with allowlist จาก ENV (ว่าง = อนุญาตทุก origin เหมือนเดิม) ===
  const allowlist = (cfg.get<string>('corsAllowlist') ?? '')
    .split(',')
    .map((s) => s.trim())
    .filter(Boolean);
  app.enableCors({
    origin: (origin, cb) => {
      if (!origin) {
        logger.debug('CORS: no-origin (curl/postman) -> allowed');
        return cb(null, true);
      }
      if (allowlist.length === 0 || allowlist.includes(origin)) {
        logger.debug(`CORS allow: ${origin}`);
        return cb(null, true);
      }
      logger.warn(`CORS block: ${origin}`);
      return cb(new Error('Not allowed by CORS'));
    },
    credentials: true,
  });

  // === Global prefix + Versioning ===
  app.setGlobalPrefix('api', {
    exclude: [
      { path: '/', method: RequestMethod.GET },
      { path: '/health', method: RequestMethod.GET },
    ],
  });
  app.enableVersioning({ type: VersioningType.URI });

  // === Global pipes/filters/interceptors ===
  app.useGlobalPipes(
    new ValidationPipe({
      whitelist: true,
      transform: true,
      transformOptions: { enableImplicitConversion: true },
      forbidUnknownValues: false,
    }),
  );
  app.useGlobalFilters(new AllExceptionsFilter());
  app.useGlobalInterceptors(
    new TimeoutInterceptor(cfg.get<number>('requestTimeoutMs', 10000)),
  );

  // === Graceful shutdown: ปิดทั้ง Nest และ Mongo ===
  app.enableShutdownHooks();
  const signals: NodeJS.Signals[] = ['SIGINT', 'SIGTERM'];
  signals.forEach((sig) => {
    process.on(sig, async () => {
      logger.warn(`Received ${sig}, shutting down gracefully...`);
      try {
        await app.close();
      } catch {}
      try {
        await mongoose.connection.close(false);
      } catch {}
      logger.log('Nest app & Mongo closed. Bye 👋');
      process.exit(0);
    });
  });
  // รองรับกรณี dev tools บางตัว (ถ้าใช้)
  process.on('SIGUSR2', async () => {
    try {
      await app.close();
    } catch {}
    try {
      await mongoose.connection.close(false);
    } catch {}
    process.exit(0);
  });

  // === จัดการ error ระดับ process ให้มีล็อกครบ ===
  process.on('uncaughtException', (err) => {
    logger.error(`uncaughtException: ${err.message}`, (err as any)?.stack);
  });
  process.on('unhandledRejection', (reason: any) => {
    logger.error(
      `unhandledRejection: ${reason?.message || reason}`,
      reason?.stack,
    );
  });

  setupSwagger(app);

  const port = cfg.get<number>('port', 4000);
  await app.listen(port);
  const url = await app.getUrl();
  console.log(`Swagger UI: ${url}/docs`);
  console.log(`OpenAPI JSON: ${url}/docs-json`);
}

bootstrap().catch((err) => {
  console.error('Bootstrap error:', err);
  process.exit(1);
});
