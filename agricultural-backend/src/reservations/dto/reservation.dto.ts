import {
  IsString,
  IsNumber,
  IsDate,
  IsEnum,
  IsOptional,
} from 'class-validator';
import { Type } from 'class-transformer';
import { ReservationStatus } from '../schemas/reservation.schema';

export class CreateReservationDto {
  @IsString()
  listing: string;

  @IsDate()
  @Type(() => Date)
  startDate: Date;

  @IsDate()
  @Type(() => Date)
  endDate: Date;

  @IsNumber()
  totalPrice: number;

  @IsOptional()
  paymentDetails?: Record<string, any>;

  @IsOptional()
  metadata?: Record<string, any>;
}

export class UpdateReservationDto {
  @IsEnum(ReservationStatus)
  @IsOptional()
  status?: ReservationStatus;

  @IsDate()
  @Type(() => Date)
  @IsOptional()
  startDate?: Date;

  @IsDate()
  @Type(() => Date)
  @IsOptional()
  endDate?: Date;

  @IsNumber()
  @IsOptional()
  totalPrice?: number;

  @IsOptional()
  paymentDetails?: Record<string, any>;

  @IsOptional()
  metadata?: Record<string, any>;
}
