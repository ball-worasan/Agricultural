import { Injectable, Logger } from '@nestjs/common';
import { AuthGuard } from '@nestjs/passport';

@Injectable()
export class JwtAuthGuard extends AuthGuard('jwt') {
  private readonly logger = new Logger(JwtAuthGuard.name);

  handleRequest(err: any, user: any, info: any, context: any, status?: any) {
    if (err || !user) {
      const req = context.switchToHttp().getRequest();
      this.logger.warn(`jwt guard blocked (path=${req?.url}, reason=${info?.message || err?.message || 'unknown'})`);
    }
    return super.handleRequest(err, user, info, context, status);
  }
}
