import { Module, Logger } from '@nestjs/common';
import { ConfigModule, ConfigService } from '@nestjs/config';
import { MongooseModule, MongooseModuleOptions } from '@nestjs/mongoose';
import configuration from './config/configuration';
import { envValidationSchema } from './config/validation';
import mongoose from 'mongoose';

import { AppController } from './app.controller';
import { AppService } from './app.service';
import { UsersModule } from './users/users.module';
import { AuthModule } from './auth/auth.module';

const mongoLogger = new Logger('Mongo');

@Module({
  imports: [
    // ไทย: โหลดคอนฟิกและตรวจ env
    ConfigModule.forRoot({
      isGlobal: true,
      load: [configuration],
      validationSchema: envValidationSchema,
      expandVariables: true,
    }),
    // ไทย: ตั้งค่าเชื่อม MongoDB แบบ async
    MongooseModule.forRootAsync({
      inject: [ConfigService],
      useFactory: async (cfg: ConfigService) => {
        const mongo = cfg.get('mongo');
        const nodeEnv = cfg.get<string>('nodeEnv');

        // ไทย: เปิด mongoose debug ด้วยฟอร์แมต log ที่อ่านง่าย
        if (mongo.debug) {
          mongoose.set(
            'debug',
            (
              coll: string,
              method: string,
              query: any,
              doc: any,
              options: any,
            ) => {
              mongoLogger.debug(
                `db.${coll}.${method} q=${JSON.stringify(query)} doc=${JSON.stringify(doc)} opt=${JSON.stringify(options)}`,
              );
            },
          );
          mongoLogger.warn('Mongoose debug mode is ON');
        }

        // ไทย: ล็อก event ของ connection ชัดเจน
        mongoose.connection.on('connected', () => mongoLogger.log('Connected'));
        mongoose.connection.on('disconnected', () =>
          mongoLogger.warn('Disconnected'),
        );
        mongoose.connection.on('reconnected', () =>
          mongoLogger.log('Reconnected'),
        );
        mongoose.connection.on('error', (e) =>
          mongoLogger.error(`Error: ${e?.message}`, e?.stack),
        );

        // ไทย: เลือกตัวบีบอัดตามไลบรารีที่มี
        const compressors: ('zstd' | 'snappy')[] = [];
        const enableZstd =
          (process.env.MONGO_ENABLE_ZSTD ?? 'false') === 'true';
        const enableSnappy =
          (process.env.MONGO_ENABLE_SNAPPY ?? 'false') === 'true';

        if (enableZstd) {
          try {
            require.resolve('@mongodb-js/zstd');
            compressors.push('zstd');
          } catch {
            mongoLogger.warn('zstd requested but package not found');
          }
        }
        if (enableSnappy) {
          try {
            require.resolve('@mongodb-js/snappy');
            compressors.push('snappy');
          } catch {
            mongoLogger.warn('snappy requested but package not found');
          }
        }

        if (compressors.length)
          mongoLogger.log(`Compressors: ${compressors.join(', ')}`);
        else mongoLogger.debug('No compressors enabled');

        // ไทย: ต่อออปชันสำคัญ (retryWrites/w) จาก ENV
        const options: MongooseModuleOptions = {
          uri: mongo.uri,
          autoIndex: nodeEnv !== 'production',
          serverSelectionTimeoutMS: mongo.serverSelectionTimeoutMS,
          socketTimeoutMS: mongo.socketTimeoutMS,
          maxPoolSize: mongo.maxPoolSize,
          minPoolSize: mongo.minPoolSize,
          maxIdleTimeMS: mongo.maxIdleTimeMS,
          retryWrites: mongo.retryWrites, // ← ENV ควบคุม
          w: mongo.w, // ← ENV ควบคุม (write concern)
          ...(compressors.length ? { compressors } : {}),
          appName: 'agricultural-rental-api',
        };

        return options;
      },
    }),
    UsersModule,
    AuthModule,
  ],
  controllers: [AppController],
  providers: [AppService],
})
export class AppModule {}
