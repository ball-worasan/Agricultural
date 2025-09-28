import { Injectable, Logger } from '@nestjs/common';

@Injectable()
export class AppService {
  private readonly logger = new Logger(AppService.name);

  getHello(): string {
    // ไทย: ล็อกจุดธุรกิจหลัก ๆ
    this.logger.verbose('Hello called');
    return 'Hello World!';
  }
}
