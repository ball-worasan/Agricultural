import {
  CallHandler,
  ExecutionContext,
  Injectable,
  NestInterceptor,
  RequestTimeoutException,
} from '@nestjs/common';
import type { Request, Response } from 'express';
import { Observable, TimeoutError, throwError } from 'rxjs';
import { catchError, timeout } from 'rxjs/operators';

@Injectable()
export class TimeoutInterceptor implements NestInterceptor {
  constructor(private readonly timeoutMs: number) {}

  intercept(context: ExecutionContext, next: CallHandler): Observable<any> {
    // ไทย: ยกเว้นบางเส้นทาง/Content-Type (เช่น SSE) ไม่ให้โดน timeout
    const http = context.switchToHttp();
    const req = http.getRequest<Request>();
    const res = http.getResponse<Response>();

    const contentType =
      (res.getHeader && (res.getHeader('Content-Type') as string)) || '';
    const isSSE =
      contentType === 'text/event-stream' ||
      req.headers.accept?.includes('text/event-stream');

    const skipPaths = ['/health', '/ready', '/metrics']; // ไทย: เพิ่มตามต้องการ
    const shouldSkip = isSSE || skipPaths.some((p) => req.url.startsWith(p));

    if (shouldSkip) {
      return next.handle(); // ไม่บังคับ timeout
    }

    return next.handle().pipe(
      timeout({
        each: this.timeoutMs,
        with: () =>
          throwError(() => new RequestTimeoutException('Request timed out')),
      }),
      catchError((err) => {
        if (err instanceof TimeoutError) {
          return throwError(
            () => new RequestTimeoutException('Request timed out'),
          );
        }
        return throwError(() => err);
      }),
    );
  }
}
