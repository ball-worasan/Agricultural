import { NestFactory } from '@nestjs/core';
import { AppModule } from './app.module';
import {
  Logger,
  LogLevel,
  ValidationPipe,
} from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import helmet from 'helmet';
import compression from 'compression';
import { AllExceptionsFilter } from './common/filters/http-exception.filter';
import { TimeoutInterceptor } from './common/interceptors/timeout.interceptor';

async function bootstrap() {
  const app = await NestFactory.create(AppModule, { bufferLogs: true });
  const cfg = app.get(ConfigService);
  const logger = new Logger('Bootstrap');

  // กำหนด log level จาก env
  const level = cfg.get<'log' | 'error' | 'warn' | 'debug' | 'verbose'>('logLevel', 'log');
  const ordered: LogLevel[] = ['log', 'error', 'warn', 'debug', 'verbose'];
  app.useLogger(ordered.slice(0, ordered.indexOf(level) + 1));

  // เพิ่ม security + performance header
  app.use(helmet());              // ป้องกัน header เสี่ยง
  app.use(compression());         // บีบอัด response

  // CORS แบบควบคุมและล็อกทุก origin
  app.enableCors({
    origin: (origin, callback) => {
      // ไทย: อนุญาตทุก origin แต่ล็อกไว้ตรวจสอบ
      if (!origin) {
        // ไทย: คำขอ direct เช่น curl/postman
        logger.debug(`CORS: no-origin (likely curl/postman) -> allowed`);
        return callback(null, true);
      }
      logger.debug(`CORS: origin=${origin} -> allowed`);
      callback(null, true);
    },
    credentials: true,
  });

  // Global pipes/filters/interceptors
  app.useGlobalPipes(new ValidationPipe({ whitelist: true, transform: true }));
  app.useGlobalFilters(new AllExceptionsFilter());
  app.useGlobalInterceptors(new TimeoutInterceptor(cfg.get<number>('requestTimeoutMs', 10000)));

  // Graceful shutdown (พร้อมล็อก)
  app.enableShutdownHooks();
  const signals: NodeJS.Signals[] = ['SIGINT', 'SIGTERM'];
  signals.forEach((sig) => {
    process.on(sig, async () => {
      logger.warn(`Received ${sig}, shutting down gracefully...`);
      await app.close();
      logger.log('Nest app closed. Bye 👋');
      process.exit(0);
    });
  });

  // จัดการ error ระดับ process ให้มีล็อกครบ
  process.on('uncaughtException', (err) => {
    logger.error(`uncaughtException: ${err.message}`, (err as any)?.stack);
  });
  process.on('unhandledRejection', (reason: any) => {
    logger.error(`unhandledRejection: ${reason?.message || reason}`, reason?.stack);
  });

  const port = cfg.get<number>('port', 3000);
  await app.listen(port);
  const url = await app.getUrl();
  logger.log(`🚀 App listening on ${url}`);
}

bootstrap().catch((err) => {
  // สำรองล็อกเผื่อ Nest logger ยังไม่พร้อม
  // eslint-disable-next-line no-console
  console.error('Bootstrap error:', err);
  process.exit(1);
});
