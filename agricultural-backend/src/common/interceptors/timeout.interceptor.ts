import {
  CallHandler,
  ExecutionContext,
  Injectable,
  NestInterceptor,
  RequestTimeoutException,
} from '@nestjs/common';
import { Observable, TimeoutError, throwError } from 'rxjs';
import { catchError, timeout } from 'rxjs/operators';

@Injectable()
export class TimeoutInterceptor implements NestInterceptor {
  constructor(private readonly timeoutMs: number) {}

  intercept(_context: ExecutionContext, next: CallHandler): Observable<any> {
    // ไทย: บังคับ timeout รายคำขอ
    return next.handle().pipe(
      timeout({
        each: this.timeoutMs,
        with: () => throwError(() => new RequestTimeoutException('Request timed out')),
      }),
      catchError((err) => {
        if (err instanceof TimeoutError) {
          return throwError(() => new RequestTimeoutException('Request timed out'));
        }
        return throwError(() => err);
      }),
    );
  }
}
