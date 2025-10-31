import { Controller, Get, Logger, Req } from '@nestjs/common';
import { AppService } from './app.service';
import type { Request } from 'express';

@Controller()
export class AppController {
  private readonly logger = new Logger(AppController.name);
  constructor(private readonly appService: AppService) {}

  @Get('/')
  getHello(@Req() req: Request): string {
    // ไทย: ตัวอย่างล็อกใน handler
    this.logger.debug(`GET / (reqId=${req.headers['x-request-id']})`);
    return this.appService.getHello();
  }

  // ไทย: health-check แบบเร็ว (ใช้กับ LB/K8s)
  @Get('/health')
  health() {
    return { ok: true, ts: new Date().toISOString() };
  }
}
