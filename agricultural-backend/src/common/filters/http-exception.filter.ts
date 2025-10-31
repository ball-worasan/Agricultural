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
    const ip = request.ip || (request.socket?.remoteAddress as string) || '-';

    // ไทย: แยก message ให้เหมาะ — ไม่ส่ง stack ออก client
    let message = 'Internal server error';
    if (exception instanceof HttpException) {
      const res = exception.getResponse();
      if (typeof res === 'string') {
        message = res;
      } else if (res && typeof res === 'object' && 'message' in (res as any)) {
        message = (res as any).message ?? exception.message;
      } else {
        message = exception.message;
      }
    }

    const payload = {
      ok: false,
      statusCode: status,
      path: request.url,
      method: request.method,
      requestId: reqId,
      ip,
      timestamp: new Date().toISOString(),
      message,
    };

    if (status >= 500) {
      // ไทย: 5xx → error พร้อม stack ใน server log เท่านั้น
      this.logger.error(
        `[${request.method}] ${request.url} (reqId=${reqId}, ip=${ip})`,
        (exception as any)?.stack ?? String(exception),
      );
    } else {
      // ไทย: 4xx → warn
      this.logger.warn(
        `[${request.method}] ${request.url} -> ${status}: ${payload.message} (reqId=${reqId}, ip=${ip})`,
      );
    }

    response.status(status).json(payload);
  }
}
