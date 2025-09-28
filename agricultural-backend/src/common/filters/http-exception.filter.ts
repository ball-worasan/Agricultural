import {
  ArgumentsHost,
  Catch,
  ExceptionFilter,
  HttpException,
  HttpStatus,
  Logger,
} from '@nestjs/common';
import { Request, Response } from 'express';

@Catch()
export class AllExceptionsFilter implements ExceptionFilter {
  private readonly logger = new Logger(AllExceptionsFilter.name);

  catch(exception: unknown, host: ArgumentsHost) {
    const ctx = host.switchToHttp();
    const response = ctx.getResponse<Response>();
    const request = ctx.getRequest<Request>();

    const status =
      exception instanceof HttpException
        ? exception.getStatus()
        : HttpStatus.INTERNAL_SERVER_ERROR;

    const reqId = (request.headers['x-request-id'] as string) || '-';
    const ip = request.ip || request.socket?.remoteAddress || '-';

    const payload = {
      ok: false,
      statusCode: status,
      path: request.url,
      method: request.method,
      requestId: reqId,
      ip,
      timestamp: new Date().toISOString(),
      message:
        exception instanceof HttpException
          ? exception.message
          : 'Internal server error',
    };

    if (status >= 500) {
      // ไทย: เซิร์ฟเวอร์พังให้ใช้ error + แนบ stack
      this.logger.error(
        `[${request.method}] ${request.url} (reqId=${reqId}, ip=${ip})`,
        (exception as any)?.stack ?? String(exception),
      );
    } else {
      // ไทย: 4xx ให้ warn พอ
      this.logger.warn(
        `[${request.method}] ${request.url} -> ${status}: ${payload.message} (reqId=${reqId}, ip=${ip})`,
      );
    }

    response.status(status).json(payload);
  }
}
